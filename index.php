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

$paymentLink = "#captura";

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

            $successMessage = "Cadastro realizado com sucesso! Em breve entraremos em contato.";
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
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink: #171716;
            --paper: #f5f1e9;
            --cream: #ebe4d5;
            --gold: #bd8b45;
            --gold-light: #d7b274;
            --muted: #746f66;
            --white: #fffdf8;
            --line: rgba(23, 23, 22, .14);
            --dark-line: rgba(255, 253, 248, .2);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body { background: var(--paper); color: var(--ink); font-family: "DM Sans", sans-serif; line-height: 1.55; }
        a { color: inherit; }
        .site { overflow: hidden; }
        .shell { width: min(1160px, 90vw); margin: 0 auto; }
        .eyebrow { color: var(--gold); font-size: .72rem; font-weight: 700; letter-spacing: .2em; text-transform: uppercase; }
        h1, h2, h3 { font-family: "Playfair Display", serif; font-weight: 600; line-height: 1.08; }
        h1 { font-size: clamp(2.7rem, 6vw, 5.75rem); letter-spacing: -.045em; max-width: 720px; }
        h2 { font-size: clamp(2rem, 4vw, 3.7rem); letter-spacing: -.035em; }
        .topbar { border-bottom: 1px solid var(--dark-line); color: var(--paper); font-size: .75rem; letter-spacing: .12em; padding: 18px 0; text-transform: uppercase; }
        .topbar .shell { align-items: center; display: flex; justify-content: space-between; }
        .hero { background: var(--ink); color: var(--paper); min-height: 760px; position: relative; }
        .hero::before { background: linear-gradient(90deg, rgba(23,23,22,.99) 15%, rgba(23,23,22,.8) 50%, rgba(23,23,22,.15)), url("https://images.unsplash.com/photo-1450101499163-c8848c66ca85?auto=format&fit=crop&w=1800&q=85") center/cover; content: ""; inset: 0; opacity: .82; position: absolute; }
        .hero-content { min-height: 680px; padding: 110px 0 80px; position: relative; }
        .hero-copy { max-width: 770px; }
        .hero h1 { margin: 24px 0; }
        .hero h1 em { color: var(--gold-light); font-style: normal; }
        .hero-lead { color: #d8d1c5; font-size: clamp(1rem, 2vw, 1.25rem); max-width: 610px; }
        .hero-meta { border-top: 1px solid var(--dark-line); display: flex; gap: 38px; margin-top: 58px; max-width: 650px; padding-top: 23px; }
        .hero-meta strong { color: var(--gold-light); display: block; font-family: "Playfair Display", serif; font-size: 1.45rem; }
        .hero-meta span { color: #bcb4a8; display: block; font-size: .78rem; margin-top: 4px; text-transform: uppercase; }
        .button { background: var(--gold); border: 1px solid var(--gold); color: var(--ink); display: inline-flex; font-size: .82rem; font-weight: 700; letter-spacing: .08em; margin-top: 32px; padding: 16px 24px; text-decoration: none; text-transform: uppercase; transition: background .2s, color .2s, transform .2s; }
        .button:hover { background: var(--gold-light); transform: translateY(-2px); }
        .button.outline { background: transparent; border-color: var(--gold); color: var(--gold-light); }
        .section { padding: 112px 0; }
        .section.dark { background: var(--ink); color: var(--paper); }
        .section-head { margin-bottom: 55px; max-width: 740px; }
        .section-head h2 { margin-top: 16px; }
        .section-head p { color: var(--muted); margin-top: 22px; max-width: 570px; }
        .dark .section-head p { color: #bcb4a8; }
        .problem { display: grid; grid-template-columns: .8fr 1.2fr; gap: 90px; }
        .problem h2 { max-width: 400px; }
        .problem-copy { color: #514d46; font-size: 1.1rem; }
        .problem-copy p + p { margin-top: 20px; }
        .problem-copy strong { color: var(--ink); }
        .numbered { border-top: 1px solid var(--line); display: grid; gap: 0; margin-top: 28px; }
        .numbered article { border-bottom: 1px solid var(--line); display: grid; gap: 25px; grid-template-columns: 55px 1fr; padding: 24px 0; }
        .numbered b { color: var(--gold); font-family: "Playfair Display", serif; font-size: 1.4rem; }
        .numbered h3 { font-size: 1.25rem; margin-bottom: 7px; }
        .numbered p { color: var(--muted); font-size: .95rem; }
        .method-grid { display: grid; gap: 14px; grid-template-columns: repeat(3, 1fr); }
        .method { border: 1px solid var(--dark-line); min-height: 250px; padding: 28px; }
        .method span { color: var(--gold-light); font-family: "Playfair Display", serif; font-size: 2rem; }
        .method h3 { font-size: 1.35rem; margin: 44px 0 10px; }
        .method p { color: #bcb4a8; font-size: .92rem; }
        .deliver { display: grid; grid-template-columns: 1fr 1fr; gap: 80px; }
        .deliver-list { list-style: none; }
        .deliver-list li { border-bottom: 1px solid var(--line); font-family: "Playfair Display", serif; font-size: 1.4rem; padding: 18px 0; }
        .deliver-list li::before { color: var(--gold); content: "✦"; font-family: "DM Sans", sans-serif; font-size: .9rem; margin-right: 15px; }
        .before-after { display: grid; grid-template-columns: 1fr 1fr; }
        .state { padding: 52px; }
        .state.before { background: #e6dfd2; }
        .state.after { background: var(--gold); }
        .state h3 { font-size: 2rem; margin: 14px 0 26px; }
        .state ul { list-style: none; }
        .state li { border-top: 1px solid rgba(23,23,22,.18); padding: 15px 0; }
        .coach { align-items: center; display: grid; gap: 80px; grid-template-columns: .8fr 1.2fr; }
        .coach img { aspect-ratio: 4 / 5; object-fit: cover; width: 100%; }
        .coach-copy p { color: #bcb4a8; font-size: 1.05rem; margin: 22px 0; max-width: 570px; }
        .credentials { border-top: 1px solid var(--dark-line); display: grid; gap: 18px; grid-template-columns: repeat(2, 1fr); margin-top: 30px; padding-top: 24px; }
        .credentials strong { color: var(--gold-light); display: block; font-family: "Playfair Display", serif; font-size: 1.55rem; }
        .credentials span { color: #bcb4a8; font-size: .82rem; }
        .offer { background: #dfd4c0; }
        .offer-wrap { align-items: center; display: grid; gap: 70px; grid-template-columns: 1.05fr .95fr; }
        .offer h2 { max-width: 520px; }
        .offer p { color: #514d46; margin-top: 20px; max-width: 490px; }
        .price-box { background: var(--ink); color: var(--paper); padding: 38px; }
        .price-box h3 { color: var(--gold-light); font-size: 1.75rem; }
        .price { font-family: "Playfair Display", serif; font-size: 3.8rem; margin: 18px 0; }
        .price small { color: #bcb4a8; font-family: "DM Sans", sans-serif; font-size: .85rem; }
        .price-box ul { color: #d8d1c5; list-style: none; }
        .price-box li { border-top: 1px solid var(--dark-line); padding: 11px 0; }
        .faq { border-top: 1px solid var(--line); }
        details { border-bottom: 1px solid var(--line); padding: 22px 0; }
        summary { cursor: pointer; font-family: "Playfair Display", serif; font-size: 1.2rem; list-style: none; }
        summary::after { color: var(--gold); content: "+"; float: right; font-family: "DM Sans", sans-serif; }
        details p { color: var(--muted); margin-top: 14px; max-width: 700px; }
        .form-wrap { background: var(--ink); color: var(--paper); padding: 100px 0; }
        .form-grid { display: grid; gap: 70px; grid-template-columns: .9fr 1.1fr; }
        .form-intro p { color: #bcb4a8; margin-top: 20px; max-width: 390px; }
        .messages { margin: 20px 0; }
        .message-ok, .message-error, .message-notice { border: 1px solid var(--gold); color: var(--paper); font-size: .9rem; margin-bottom: 8px; padding: 10px 12px; }
        form { display: grid; gap: 18px; grid-template-columns: 1fr 1fr; }
        .field { display: grid; gap: 7px; }
        .field.full, .integracao-note { grid-column: 1 / -1; }
        label { color: #d8d1c5; font-size: .78rem; letter-spacing: .08em; text-transform: uppercase; }
        input { background: transparent; border: 0; border-bottom: 1px solid var(--dark-line); color: var(--white); font: inherit; outline: 0; padding: 12px 0; width: 100%; }
        input:focus { border-color: var(--gold-light); }
        input::placeholder { color: #827b70; }
        .integracao-note { color: #827b70; font-size: .78rem; }
        footer { background: var(--ink); border-top: 1px solid var(--dark-line); color: #827b70; font-size: .78rem; padding: 25px 0; text-align: center; }
        @media (max-width: 780px) {
            .topbar .shell { align-items: flex-start; flex-direction: column; gap: 7px; }
            .hero { min-height: 650px; }
            .hero-content { min-height: 570px; padding-top: 75px; }
            .hero-meta { gap: 18px; margin-top: 42px; }
            .problem, .deliver, .coach, .offer-wrap, .form-grid { gap: 42px; grid-template-columns: 1fr; }
            .section { padding: 78px 0; }
            .method-grid, .before-after { grid-template-columns: 1fr; }
            .state { padding: 35px 25px; }
            .coach img { max-height: 470px; }
            form { grid-template-columns: 1fr; }
            .field.full, .integracao-note { grid-column: auto; }
        }
        .event-page { background: #160d0c; color: #fff8ed; font-family: "DM Sans", sans-serif; }
        .event-shell { margin: 0 auto; max-width: 1160px; width: min(92vw, 1160px); }
        .event-hero { background: linear-gradient(90deg, rgba(22, 13, 12, .98), rgba(54, 25, 21, .72)), url("imgs/tema.jpeg") center / cover; min-height: 720px; padding: 28px 0 90px; }
        .event-nav { align-items: center; border-bottom: 1px solid rgba(227, 180, 100, .35); display: flex; justify-content: space-between; padding-bottom: 20px; }
        .event-brand { color: #e0b96f; font-family: "Playfair Display", serif; font-size: 1.15rem; letter-spacing: .06em; text-transform: uppercase; }
        .event-nav a { color: #fff8ed; font-size: .78rem; font-weight: 700; letter-spacing: .12em; text-decoration: none; text-transform: uppercase; }
        .event-hero-copy { max-width: 760px; padding: 120px 0 0; }
        .event-kicker { color: #e0b96f; font-size: .8rem; font-weight: 700; letter-spacing: .2em; text-transform: uppercase; }
        .event-hero h1 { color: #f0c678; font-family: "Playfair Display", serif; font-size: clamp(3rem, 7vw, 6.6rem); letter-spacing: -.045em; line-height: .98; margin: 22px 0; }
        .event-hero h1 span { color: #fff8ed; display: block; }
        .event-hero p { color: #e8d8c6; font-size: clamp(1.05rem, 2vw, 1.35rem); max-width: 620px; }
        .event-facts { border-top: 1px solid rgba(227, 180, 100, .45); display: grid; gap: 18px; grid-template-columns: repeat(3, 1fr); margin-top: 48px; padding-top: 22px; }
        .event-facts strong { color: #e0b96f; display: block; font-family: "Playfair Display", serif; font-size: 1.5rem; }
        .event-facts small { color: #d8c6b4; font-size: .76rem; letter-spacing: .08em; text-transform: uppercase; }
        .event-button { background: #d9ae60; border: 1px solid #f0cc88; color: #21100d; display: inline-flex; font-size: .8rem; font-weight: 800; letter-spacing: .1em; margin-top: 30px; padding: 16px 25px; text-decoration: none; text-transform: uppercase; transition: transform .2s, background .2s; }
        .event-button:hover { background: #f0cc88; transform: translateY(-2px); }
        .event-section { background: #211211; padding: 92px 0; }
        .event-section.alt { background: #351a17; }
        .event-heading { color: #e8bd75; font-family: "Playfair Display", serif; font-size: clamp(2.2rem, 5vw, 4rem); letter-spacing: -.04em; line-height: 1.05; margin-bottom: 34px; max-width: 720px; }
        .event-heading span { color: #fff8ed; }
        .event-art { border: 1px solid rgba(224, 185, 111, .55); display: block; height: auto; margin: 0 auto; max-width: 100%; width: 520px; }
        .event-art-grid { align-items: center; display: grid; gap: 55px; grid-template-columns: 1fr 1fr; }
        .event-art-grid .event-art { width: 100%; }
        .event-copy { color: #e4d4c4; font-size: 1.08rem; max-width: 570px; }
        .event-copy strong { color: #e0b96f; }
        .event-cronograma { background: #160d0c; padding: 92px 0; }
        .event-cronograma .event-art { width: min(100%, 560px); }
        .event-price { background: #241211; padding: 92px 0; text-align: center; }
        .event-price .event-art { width: min(100%, 520px); }
        .event-price .event-button { display: inline-flex; }
        .event-faq { border-top: 1px solid rgba(224, 185, 111, .4); margin: 0 auto; max-width: 760px; text-align: left; }
        .event-faq details { border-bottom: 1px solid rgba(224, 185, 111, .3); padding: 20px 0; }
        .event-faq summary { color: #fff8ed; cursor: pointer; font-size: 1rem; font-weight: 700; list-style: none; }
        .event-faq summary::after { color: #e0b96f; content: "+"; float: right; font-size: 1.4rem; }
        .event-faq p { color: #d8c6b4; margin-top: 12px; }
        .event-form { background: #160d0c; padding: 92px 0; }
        .event-form-grid { align-items: start; display: grid; gap: 60px; grid-template-columns: .9fr 1.1fr; }
        .event-form h2 { color: #e8bd75; font-family: "Playfair Display", serif; font-size: clamp(2.2rem, 4vw, 3.6rem); line-height: 1.05; }
        .event-form-intro p { color: #d8c6b4; margin-top: 18px; max-width: 400px; }
        .event-form form { display: grid; gap: 18px; grid-template-columns: 1fr 1fr; }
        .event-form .field { display: grid; gap: 7px; }
        .event-form .field.full, .event-form .integracao-note { grid-column: 1 / -1; }
        .event-form label { color: #e8d8c6; font-size: .75rem; letter-spacing: .1em; text-transform: uppercase; }
        .event-form input { background: transparent; border: 0; border-bottom: 1px solid rgba(224, 185, 111, .45); color: #fff8ed; font: inherit; outline: 0; padding: 12px 0; width: 100%; }
        .event-form input:focus { border-color: #f0cc88; }
        .event-form input::placeholder { color: #927c70; }
        .event-form .integracao-note { color: #927c70; font-size: .78rem; }
        .event-message { border: 1px solid #d9ae60; color: #fff8ed; font-size: .9rem; margin-bottom: 12px; padding: 10px; }
        .event-footer { background: #160d0c; border-top: 1px solid rgba(224, 185, 111, .35); color: #927c70; font-size: .78rem; padding: 24px 0; text-align: center; }
        @media (max-width: 760px) {
            .event-hero { min-height: 680px; }
            .event-hero-copy { padding-top: 90px; }
            .event-nav { align-items: flex-start; flex-direction: column; gap: 12px; }
            .event-facts { gap: 12px; grid-template-columns: 1fr; }
            .event-art-grid, .event-form-grid { gap: 34px; grid-template-columns: 1fr; }
            .event-section, .event-cronograma, .event-price, .event-form { padding: 68px 0; }
            .event-form form { grid-template-columns: 1fr; }
            .event-form .field.full, .event-form .integracao-note { grid-column: auto; }
        }
    </style>
</head>
<body>
    <div class="event-page">
        <header class="event-hero" id="topo"><div class="event-shell"><nav class="event-nav"><span class="event-brand">Base Criminal</span><a href="#ingresso">Garantir ingresso</a></nav><div class="event-hero-copy"><span class="event-kicker">Formação prática criminal</span><h1>Imersão da <span>Base Criminal</span></h1><p>Do flagrante à liberdade: estratégia e atuação nas primeiras horas da defesa criminal.</p><a class="event-button" href="#ingresso">Quero garantir minha vaga</a><div class="event-facts"><div><strong>26 de setembro</strong><small>das 09h às 18h</small></div><div><strong>45 vagas</strong><small>encontro exclusivo</small></div><div><strong>Vila Andrade</strong><small>São Paulo / SP</small></div></div></div></div></header>
        <main>
            <section class="event-section"><div class="event-shell event-art-grid"><div><span class="event-kicker">O tema você já descobriu</span><h2 class="event-heading">A defesa começa <span>antes do processo.</span></h2><p class="event-copy">Uma experiência prática de análise, estratégia e atuação criminal, partindo do flagrante até os primeiros pedidos de liberdade.</p><a class="event-button" href="#ingresso">Inscrições abertas</a></div><img class="event-art" src="imgs/a-defesa.jpeg" alt="A defesa começa antes do processo"></div></section>
            <section class="event-section alt"><div class="event-shell"><span class="event-kicker">O que você vai levar da imersão</span><h2 class="event-heading">Casos reais. <span>Estratégia real.</span></h2><img class="event-art" src="imgs/o-que-vai-levar.jpeg" alt="Conteúdos e aprendizados da imersão"></div></section>
            <section class="event-cronograma"><div class="event-shell"><span class="event-kicker">Um dia inteiro de prática criminal</span><h2 class="event-heading">Cronograma da <span>imersão</span></h2><img class="event-art" src="imgs/cronograma.jpeg" alt="Cronograma da Imersão da Base Criminal"></div></section>
            <section class="event-section"><div class="event-shell"><span class="event-kicker">Quem estará com a gente</span><h2 class="event-heading">Dois profissionais. <span>Duas experiências.</span></h2><div class="event-art-grid"><img class="event-art" src="imgs/quem-estara-com-a-gente-1.jpeg" alt="Dr. Bruno Santana e Dr. Matheus Alexandre, palestrantes da imersão"><img class="event-art" src="imgs/quem-estara-com-a-gente-2.jpeg" alt="Experiência profissional dos palestrantes"></div></div></section>
            <section class="event-section alt"><div class="event-shell"><span class="event-kicker">Dúvidas frequentes</span><h2 class="event-heading">Tudo o que você precisa <span>saber.</span></h2><div class="event-faq"><details><summary>Qual será o tema da imersão?</summary><p>Do Flagrante à Liberdade: estratégia e atuação nas primeiras horas da defesa criminal.</p></details><details><summary>Quando e onde será?</summary><p>26 de setembro, das 09h às 18h, na Av. Giovanni Gronchi, 6195, Vila Andrade, São Paulo/SP.</p></details><details><summary>Quem pode participar?</summary><p>Estudantes e profissionais que tenham interesse em Direito Penal e prática criminal.</p></details><details><summary>Vai ter brinde?</summary><p>Sim. Todos os participantes receberão um brinde. Os 20 primeiros inscritos terão um brinde diferenciado e condição especial.</p></details></div></div></section>
            <section class="event-price" id="ingresso"><div class="event-shell"><span class="event-kicker">Apenas 45 vagas</span><h2 class="event-heading">Encontro <span>exclusivo</span></h2><img class="event-art" src="imgs/valor.jpeg" alt="Ingresso da Imersão da Base Criminal por R$ 397,00"><a class="event-button" href="<?php echo h($paymentLink); ?>">Quero garantir meu ingresso por R$ 397,00</a></div></section>
            <section class="event-form" id="captura" aria-labelledby="form-captura"><div class="event-shell event-form-grid"><div class="event-form-intro"><span class="event-kicker">Garanta sua vaga</span><h2 id="form-captura">Inscrições abertas para a Imersão da Base Criminal.</h2><p>Preencha seus dados para garantir o contato sobre o ingresso e receber as informações do encontro.</p><img class="event-art" src="imgs/duvidas-frequentes-1.jpeg" alt="Informações sobre a Imersão da Base Criminal"></div><div><div class="messages" aria-live="polite"><?php if ($successMessage !== ""): ?><p class="event-message"><?php echo h($successMessage); ?></p><?php endif; ?><?php if ($noticeMessage !== ""): ?><p class="event-message"><?php echo h($noticeMessage); ?></p><?php endif; ?><?php foreach ($errors as $error): ?><p class="event-message"><?php echo h($error); ?></p><?php endforeach; ?></div><form method="post" action="#captura" novalidate><div class="field"><label for="nome">Nome completo</label><input type="text" id="nome" name="nome" placeholder="Seu nome" required value="<?php echo h($formData["nome"]); ?>"></div><div class="field"><label for="empresa">Instituição / escritório</label><input type="text" id="empresa" name="empresa" placeholder="Nome da instituição" required value="<?php echo h($formData["empresa"]); ?>"></div><div class="field"><label for="whatsapp">WhatsApp</label><input type="tel" id="whatsapp" name="whatsapp" placeholder="(11) 99999-9999" required value="<?php echo h($formData["whatsapp"]); ?>"></div><div class="field"><label for="email">E-mail</label><input type="email" id="email" name="email" placeholder="voce@email.com" required value="<?php echo h($formData["email"]); ?>"></div><p class="integracao-note">Seus dados serão usados para contato sobre a inscrição.</p><div class="field full"><button class="event-button" type="submit">Garantir minha vaga</button></div></form></div></div></section>
            <section class="event-section"><div class="event-shell"><img class="event-art" src="imgs/agradecimentos.jpeg" alt="Obrigado por fazer parte da Imersão da Base Criminal"></div></section>
        </main>
        <footer class="event-footer">Base Criminal | Formação Prática Criminal</footer>
    </div>
    <script>
        (function () {
            var whatsappInput = document.getElementById("whatsapp");
            if (!whatsappInput) return;
            whatsappInput.addEventListener("input", function (event) {
                var digits = event.target.value.replace(/\D/g, "").slice(0, 11);
                var masked = digits;
                if (digits.length > 2) masked = "(" + digits.slice(0, 2) + ") " + digits.slice(2);
                if (digits.length > 7) masked = "(" + digits.slice(0, 2) + ") " + digits.slice(2, 7) + "-" + digits.slice(7);
                event.target.value = masked;
            });
        })();
    </script>
</body>
</html>
