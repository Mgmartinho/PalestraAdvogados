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
