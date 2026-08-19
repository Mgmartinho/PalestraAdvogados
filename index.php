<?php
declare(strict_types=1);

/**
 * Template Name: Landing Page Palestra
 * Description: Landing page para captacao de leads e venda de palestra.
 */

$errors = [];
$successMessage = "";
$noticeMessage = "";

$formData = [
    "nome" => "",
    "empresa" => "",
    "whatsapp" => "",
    "email" => "",
];

$paymentLink = "https://pag.ae/8251kCtcP";
$paymentUnlocked = false;
$eventWhatsappNumber = preg_replace("/\D+/", "", (string) getenv("EVENT_WHATSAPP_NUMBER"));
$eventWhatsappMessage = rawurlencode("Olá! Gostaria de tirar uma dúvida sobre a Imersão da Base Criminal.");
$eventWhatsappLink = $eventWhatsappNumber !== ""
    ? "https://wa.me/" . $eventWhatsappNumber . "?text=" . $eventWhatsappMessage
    : "#captura";

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, "UTF-8");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $formData["nome"] = trim((string) ($_POST["nome"] ?? ""));
    $formData["empresa"] = trim((string) ($_POST["empresa"] ?? ""));
    $formData["whatsapp"] = trim((string) ($_POST["whatsapp"] ?? ""));
    $formData["email"] = trim((string) ($_POST["email"] ?? ""));

    if ($formData["nome"] === "") {
        $errors[] = "Informe seu nome.";
    }

    if ($formData["empresa"] === "") {
        $errors[] = "Informe sua empresa.";
    }

    if ($formData["whatsapp"] === "") {
        $errors[] = "Informe seu WhatsApp.";
    }

    if (!filter_var($formData["email"], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Informe um e-mail valido.";
    }

    if (!$errors) {
        try {
            $isVercel = getenv("VERCEL") === "1";
            $storageDir = $isVercel
                ? sys_get_temp_dir() . DIRECTORY_SEPARATOR . "palestra-advogados"
                : __DIR__ . DIRECTORY_SEPARATOR . "storage";
            if (!is_dir($storageDir) && !mkdir($storageDir, 0755, true) && !is_dir($storageDir)) {
                throw new RuntimeException("Nao foi possivel criar a pasta de armazenamento.");
            }

            $timestamp = date("Y-m-d H:i:s");

            $csvPath = $storageDir . DIRECTORY_SEPARATOR . "leads.csv";
            $csvFileExists = file_exists($csvPath) && filesize($csvPath) > 0;
            $csvHandle = fopen($csvPath, "ab");

            if ($csvHandle === false) {
                throw new RuntimeException("Nao foi possivel abrir o arquivo CSV.");
            }

            if (!$csvFileExists) {
                fputcsv($csvHandle, ["nome", "empresa", "whatsapp", "email", "origem", "capturado_em"]);
            }

            fputcsv($csvHandle, [
                $formData["nome"],
                $formData["empresa"],
                $formData["whatsapp"],
                $formData["email"],
                "mentoria-advocacia",
                $timestamp,
            ]);

            fclose($csvHandle);

            $webhookUrl = trim((string) getenv("LEADS_WEBHOOK_URL"));
            if ($webhookUrl !== "") {
                $payload = json_encode([
                    "nome" => $formData["nome"],
                    "empresa" => $formData["empresa"],
                    "whatsapp" => $formData["whatsapp"],
                    "email" => $formData["email"],
                    "origem" => "mentoria-advocacia",
                    "capturado_em" => $timestamp,
                ], JSON_THROW_ON_ERROR);

                $context = stream_context_create([
                    "http" => [
                        "method" => "POST",
                        "header" => "Content-Type: application/json\r\n",
                        "content" => $payload,
                        "timeout" => 8,
                        "ignore_errors" => true,
                    ],
                ]);
                $webhookResponse = @file_get_contents($webhookUrl, false, $context);
                $statusLine = $http_response_header[0] ?? "";
                if ($webhookResponse === false || !preg_match("/\\s2\\d{2}\\s/", $statusLine)) {
                    throw new RuntimeException("Nao foi possivel enviar o cadastro para a integracao.");
                }
            } elseif ($isVercel) {
                $noticeMessage = "Configure LEADS_WEBHOOK_URL na Vercel para persistir os cadastros.";
            }

            if (!$isVercel && extension_loaded("pdo_sqlite")) {
                $sqlitePath = $storageDir . DIRECTORY_SEPARATOR . "leads.sqlite";
                $pdo = new PDO("sqlite:" . $sqlitePath);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                $pdo->exec(
                    "CREATE TABLE IF NOT EXISTS leads (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        nome TEXT NOT NULL,
                        empresa TEXT NOT NULL,
                        whatsapp TEXT NOT NULL,
                        email TEXT NOT NULL,
                        origem TEXT NOT NULL,
                        capturado_em TEXT NOT NULL
                    )"
                );

                $stmt = $pdo->prepare(
                    "INSERT INTO leads (nome, empresa, whatsapp, email, origem, capturado_em)
                     VALUES (:nome, :empresa, :whatsapp, :email, :origem, :capturado_em)"
                );

                $stmt->execute([
                    ":nome" => $formData["nome"],
                    ":empresa" => $formData["empresa"],
                    ":whatsapp" => $formData["whatsapp"],
                    ":email" => $formData["email"],
                    ":origem" => "mentoria-advocacia",
                    ":capturado_em" => $timestamp,
                ]);
            } elseif (!$isVercel && !extension_loaded("pdo_sqlite")) {
                $noticeMessage = "Cadastro salvo no CSV. SQLite nao esta ativo neste ambiente.";
            }

            $notificationEmail = trim((string) getenv("LEADS_NOTIFICATION_EMAIL"));
            if ($notificationEmail !== "" && filter_var($notificationEmail, FILTER_VALIDATE_EMAIL)) {
                $mailSubject = "Novo cadastro - Imersao da Base Criminal";
                $mailBody = implode(PHP_EOL, [
                    "Novo cadastro recebido:",
                    "Nome: " . $formData["nome"],
                    "Instituicao / escritorio: " . $formData["empresa"],
                    "WhatsApp: " . $formData["whatsapp"],
                    "E-mail: " . $formData["email"],
                    "Capturado em: " . $timestamp,
                ]);
                $mailFrom = trim((string) getenv("MAIL_FROM"));
                $mailHeaders = "Content-Type: text/plain; charset=UTF-8\r\n";
                if ($mailFrom !== "" && filter_var($mailFrom, FILTER_VALIDATE_EMAIL)) {
                    $mailHeaders .= "From: " . $mailFrom . "\r\n";
                }
                $mailSent = function_exists("wp_mail")
                    ? wp_mail($notificationEmail, $mailSubject, $mailBody, $mailHeaders)
                    : mail($notificationEmail, $mailSubject, $mailBody, $mailHeaders);
                if (!$mailSent) {
                    $noticeMessage = "Cadastro salvo, mas o aviso por e-mail nao foi enviado.";
                }
            } else {
                $noticeMessage = "Configure LEADS_NOTIFICATION_EMAIL para receber os cadastros por e-mail.";
            }

            $successMessage = "Cadastro realizado com sucesso! Em breve entraremos em contato.";
            $paymentUnlocked = true;
            $formData = ["nome" => "", "empresa" => "", "whatsapp" => "", "email" => ""];
        } catch (Throwable $exception) {
            $errors[] = "Nao foi possivel registrar seu cadastro agora. Tente novamente em alguns instantes.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Imersão da Base Criminal | Formação Prática Criminal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Playfair+Display:wght@500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="<?php echo esc_url(get_template_directory_uri() . '/style.css'); ?>">

</head>

<body>
    <div class="event-page">
        <header class="event-hero" id="topo">
            <div class="event-shell">
                <nav class="event-nav"><span class="event-brand"><img src="<?php echo esc_url(get_template_directory_uri() . '/imgs/Logo-transparent.png'); ?>" alt="Logo Base Criminal"></span><a href="#ingresso">Garantir
                        ingresso</a></nav>
                        <div class="event-hero-copy"><span class="event-kicker"></span>
                            <h1>Imersão da <span>Base Criminal</span></h1>
                    <p>Do flagrante à liberdade: estratégia e atuação nas primeiras horas da defesa criminal.</p><a
                        class="event-button" href="#ingresso">Quero garantir minha vaga</a>
                    <div class="event-facts">
                        <div><strong>26 de setembro</strong><small>das 09h às 18h</small></div>
                        <div><strong>45 vagas</strong><small>encontro exclusivo</small></div>
                        <div><strong>Vila Andrade</strong><small>São Paulo / SP</small></div>
                    </div>
                </div>
            </div>
        </header>
        <main>
            <section class="event-section">
                <div class="event-shell">
                    <span class="event-kicker">O tema você já descobriu</span>
                    <h2 class="event-heading">A defesa começa <span>antes do processo.</span></h2>
                    <p class="event-copy">Uma experiência prática de análise, estratégia e atuação criminal,
                        partindo do flagrante até os primeiros pedidos de liberdade.</p><a class="event-button"
                        href="#ingresso">Inscrições abertas</a>
                </div>
            </section>

            <section class="event-section alt">
                <div class="event-shell content-section"><span class="event-kicker">O que você vai levar da imersão</span>
                    <h2 class="event-heading">Casos reais. <span>Estratégia real.</span></h2>
                    <div class="content-feature-grid">
                        <img class="event-art" src="<?php echo esc_url(get_template_directory_uri() . '/imgs/jail.jpeg'); ?>" alt="Cela de uma prisão">
                        <ul class="content-list">
                            <li>Análise de casos reais de flagrante</li>
                            <li>Estratégias de defesa nas primeiras horas</li>
                            <li>Simulação de audiência de custódia</li>
                            <li>Modelos de petições e pedidos de liberdade</li>
                            <li>Networking com profissionais da área criminal</li>
                        </ul>
                    </div>
                </div>
            </section>

            <section class="event-cronograma">
                <div class="event-shell cronograma-grid">
                    <div>
                        <span class="event-kicker">Um dia inteiro de prática criminal</span>
                        <h2 class="event-heading">Cronograma da <span>imersão</span></h2>
                        <ul class="schedule-list">
                            <li><span class="schedule-time">09h</span><span class="schedule-label">Chegada, credenciamento e coffee break</span></li>
                            <li><span class="schedule-time">09h30</span><span class="schedule-label">Começa a imersão</span></li>
                            <li><span class="schedule-time">12h</span><span class="schedule-label">Pausa para almoço</span></li>
                            <li><span class="schedule-time">14h</span><span class="schedule-label">Retorno</span></li>
                            <li><span class="schedule-time">16h</span><span class="schedule-label">Coffee break</span></li>
                            <li><span class="schedule-time">18h</span><span class="schedule-label">Encerramento e happy hour</span></li>
                        </ul>
                    </div>
                    <div class="address-card">
                        <h3>Endereço</h3>
                        <div class="address-fact"><strong>26 de setembro</strong><span>Data do encontro</span></div>
                        <div class="address-fact"><strong>Das 09h às 18h</strong><span>Horário</span></div>
                        <div class="address-fact"><strong>Av. Giovanni Gronchi, 6195</strong><span>Vila Andrade / São Paulo, SP</span></div>
                    </div>
                </div>
            </section>

            <section class="event-section">
                <div class="event-shell"><span class="event-kicker">Quem estará com a gente</span>
                    <h2 class="event-heading">Dois profissionais. <br/>
                        <span>Duas experiências.</span></h2>
                    <div class="speaker-grid">
                        <div class="speaker-card">
                            <img src="<?php echo esc_url(get_template_directory_uri() . '/imgs/quem-estara-com-a-gente-1.jpeg'); ?>" alt="Dr. Bruno Santana, advogado criminalista">
                            <h3>Dr. Bruno Santana</h3>
                            <span>Advogado criminalista | Estratégia e defesa de urgência</span>
                            <p class="speaker-trajectory">Com 14 anos de atuação fictícia na advocacia criminal, Bruno construiu sua trajetória acompanhando prisões em flagrante, audiências de custódia e pedidos de liberdade em diferentes fases do processo.</p>
                            <ul class="speaker-highlights">
                                <li><strong>14 anos</strong><span>de advocacia</span></li>
                                <li><strong>+320</strong><span>casos acompanhados</span></li>
                            </ul>
                        </div>
                        <div class="speaker-card">
                            <img src="<?php echo esc_url(get_template_directory_uri() . '/imgs/quem-estara-com-a-gente-2.jpeg'); ?>" alt="Dr. Matheus Alexandre, advogado criminalista">
                            <h3>Dr. Matheus Alexandre</h3>
                            <span>Advogado criminalista | Prática e formação profissional</span>
                            <p class="speaker-trajectory">Com uma trajetória fictícia de 11 anos no Direito Penal, Matheus atua na construção de estratégias defensivas e na formação prática de novos profissionais para decisões mais seguras desde o primeiro atendimento.</p>
                            <ul class="speaker-highlights">
                                <li><strong>11 anos</strong><span>de experiência</span></li>
                                <li><strong>+40</strong><span>turmas orientadas</span></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </section>

            <section class="event-section alt">
                <div class="event-shell"><span class="event-kicker">Dúvidas frequentes</span>
                    <h2 class="event-heading">Tudo o que você precisa <span>saber.</span></h2>
                    <div class="event-faq">
                        <details>
                            <summary>Qual será o tema da imersão?</summary>
                            <p>Do Flagrante à Liberdade: estratégia e atuação nas primeiras horas da defesa criminal.
                            </p>
                        </details>
                        <details>
                            <summary>Quando e onde será?</summary>
                            <p>26 de setembro, das 09h às 18h, na Av. Giovanni Gronchi, 6195, Vila Andrade, São
                                Paulo/SP.</p>
                        </details>
                        <details>
                            <summary>Quem pode participar?</summary>
                            <p>Estudantes e profissionais que tenham interesse em Direito Penal e prática criminal.</p>
                        </details>
                        <details>
                            <summary>Vai ter brinde?</summary>
                            <p>Sim. Todos os participantes receberão um brinde. Os 20 primeiros inscritos terão um
                                brinde diferenciado e condição especial.</p>
                        </details>
                    </div>
                </div>
            </section>

            <section class="event-price" id="ingresso">
                <div class="event-shell"><span class="event-kicker">Apenas 45 vagas</span>
                    <h2 class="event-heading">Encontro <span>exclusivo</span></h2>
                    <div class="ticket-box">
                        <p class="ticket-price">R$ 397<small>,00 à vista</small></p>
                        <ul class="ticket-list">
                            <li>Acesso completo à imersão (09h às 18h)</li>
                            <li>Coffee break e almoço inclusos</li>
                            <li>Material de apoio exclusivo</li>
                            <li>Certificado de participação</li>
                            <li>Brinde exclusivo de edição limitada</li>
                        </ul>
                    </div><?php if ($paymentUnlocked): ?><a class="event-button" href="<?php echo esc_url($paymentLink); ?>" target="_blank" rel="noopener">Quero garantir meu ingresso por R$
                        397,00</a><?php else: ?><a class="event-button" href="#captura">Preencha o formulário para continuar</a><?php endif; ?>
                </div>
            </section>

            <section class="event-form" id="captura" aria-labelledby="form-captura">
                <div class="event-shell event-form-grid">
                    <div class="event-form-intro"><span class="event-kicker">Garanta sua vaga</span>
                        <h2 id="form-captura">Inscrições abertas para a Imersão da Base Criminal.</h2>
                        <p>Preencha seus dados para garantir o contato sobre o ingresso e receber as informações do
                            encontro.</p>
                    </div>
                    <div>
                        <div class="messages" aria-live="polite"><?php if ($successMessage !== ""): ?>
                                <p class="event-message"><?php echo h($successMessage); ?></p>
                            <?php endif; ?><?php if ($noticeMessage !== ""): ?>
                                <p class="event-message"><?php echo h($noticeMessage); ?></p>
                            <?php endif; ?><?php foreach ($errors as $error): ?>
                                <p class="event-message"><?php echo h($error); ?></p><?php endforeach; ?>
                        </div>
                        <form method="post" action="#captura" novalidate>
                            <div class="field"><label for="nome">Nome completo</label><input type="text" id="nome"
                                    name="nome" placeholder="Seu nome" required
                                    value="<?php echo h($formData["nome"]); ?>"></div>
                            <div class="field"><label for="empresa">Instituição / escritório</label><input type="text"
                                    id="empresa" name="empresa" placeholder="Nome da instituição" required
                                    value="<?php echo h($formData["empresa"]); ?>"></div>
                            <div class="field"><label for="whatsapp">WhatsApp</label><input type="tel" id="whatsapp"
                                    name="whatsapp" placeholder="(11) 99999-9999" required
                                    value="<?php echo h($formData["whatsapp"]); ?>"></div>
                            <div class="field"><label for="email">E-mail</label><input type="email" id="email"
                                    name="email" placeholder="voce@email.com" required
                                    value="<?php echo h($formData["email"]); ?>"></div>
                            <p class="integracao-note">Seus dados serão usados para contato sobre a inscrição.</p>
                            <div class="field full"><button class="event-button" type="submit">Garantir minha
                                    vaga</button></div>
                            <?php if ($paymentUnlocked): ?>
                                <div class="field full"><a class="event-button" href="<?php echo esc_url($paymentLink); ?>" target="_blank" rel="noopener">Ir para o pagamento PagBank</a></div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </section>

            <section class="next-immersion">
                <div class="event-shell"><span class="event-kicker">Prepare-se</span>
                    <h2 class="event-heading">Fique <span>ligado!</span></h2>
                    <p>A nossa primeira Imersão foi só o começo. Novas experiências, novos casos e muito conteúdo
                        ainda estão por vir.</p>
                    <p>Fique de olho nas próximas Imersões da Base Criminal e não perca a oportunidade de estar com a
                        gente nos próximos encontros.</p>
                    <div class="teaser-box"><span class="event-kicker">Em breve, teremos novidades</span>
                        <p class="teaser-lead">A defesa começa antes do processo</p>
                        <h3>Imersão da Base Criminal<span>Do flagrante à liberdade</span></h3>
                        <p>E você vai aprender a pensar estrategicamente desde as primeiras horas.</p>
                        <div class="teaser-facts">
                            <div><strong>29 de setembro</strong><span>Data</span></div>
                            <div><strong>09h às 18h</strong><span>Horário</span></div>
                        </div><a class="event-button" href="#ingresso">Inscrições abertas</a>
                    </div>
                </div>
            </section>
            
        </main>
        <footer class="event-footer">Base Criminal | Formação Prática Criminal</footer>
    </div>
    <a class="whatsapp-float" href="<?php echo esc_url($eventWhatsappLink); ?>" aria-label="Falar sobre o evento pelo WhatsApp" target="_blank" rel="noopener">
        <img class="whatsapp-float-icon" src="<?php echo esc_url(get_template_directory_uri() . '/imgs/whatsapp.jpg'); ?>" alt="">
    </a>
    <script src="<?php echo esc_url(get_template_directory_uri() . '/script.js'); ?>"></script>
</body>

</html>