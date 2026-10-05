(function () {
    document.addEventListener('DOMContentLoaded', function () {
        var cfg = window.atareao_form_challenge;
        if (!cfg || !cfg.ajax_url || !cfg.context) {
            return;
        }

        var isContact = cfg.context === 'contact';
        var form = isContact
            ? document.querySelector('.atareao-contact-form')
            : (document.getElementById('commentform') || document.querySelector('#respond form'));
        if (!form) {
            return;
        }

        var body = new URLSearchParams();
        body.append('action', cfg.action || 'atareao_form_challenge');
        body.append('context', cfg.context);

        fetch(cfg.ajax_url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: body.toString()
        }).then(function (res) {
            return res.json();
        }).then(function (json) {
            if (!json || !json.success || !json.data) {
                return;
            }
            var data = json.data;
            var setValue = function (name, value) {
                var input = form.querySelector('input[name="' + name + '"]');
                if (input) {
                    input.value = value;
                }
            };
            if (isContact) {
                setValue('atareao_form_time', data.time);
                setValue('atareao_captcha_a', data.a);
                setValue('atareao_captcha_b', data.b);
                setValue('atareao_captcha_sig', data.sig);
                setValue('atareao_contact_nonce', data.nonce);
                var contactLabel = form.querySelector('label[for="atareao_captcha_answer"]');
                if (contactLabel) {
                    contactLabel.textContent = '¿Cuanto es ' + data.a + ' + ' + data.b + '?';
                }
            } else {
                setValue('atareao_comment_form_time', data.time);
                setValue('atareao_comment_captcha_a', data.a);
                setValue('atareao_comment_captcha_b', data.b);
                setValue('atareao_comment_captcha_sig', data.sig);
                if (window.atareao_ajax) {
                    window.atareao_ajax.nonce = data.nonce;
                }
                var commentLabel = form.querySelector('label[for="atareao_comment_captcha"]');
                if (commentLabel) {
                    var required = document.createElement('span');
                    required.className = 'required';
                    required.textContent = '*';
                    commentLabel.textContent = '¿Cuánto es ' + data.a + ' + ' + data.b + '? ';
                    commentLabel.appendChild(required);
                }
            }
        }).catch(function () {
            // Network error: keep the server-rendered challenge as fallback.
        });
    });
})();
