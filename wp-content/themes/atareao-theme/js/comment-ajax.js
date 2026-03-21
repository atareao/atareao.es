(function(){
    document.addEventListener('DOMContentLoaded', function(){
        var form = document.getElementById('commentform') || document.querySelector('#respond form');
        if (!form || typeof window.atareao_ajax === 'undefined') return; // guard

        var messageEl = document.getElementById('atareao-comment-message');
        if (!messageEl) {
            messageEl = document.createElement('div');
            messageEl.id = 'atareao-comment-message';
            messageEl.setAttribute('role','status');
            messageEl.setAttribute('aria-live','polite');
            var respond = document.getElementById('respond');
            if (respond) respond.insertBefore(messageEl, respond.firstChild);
        }

        // Intercept WordPress reply links to avoid moving the actual form.
        document.addEventListener('click', function(e){
            var link = e.target.closest && e.target.closest('.comment-reply-link');
            if (!link) return;
            // Prevent WP's moveComment from running
            e.preventDefault();
            e.stopImmediatePropagation();

            var parentId = parseInt(link.getAttribute('data-commentid') || link.getAttribute('data-comment-id') || (link.dataset && link.dataset.commentid) || 0, 10) || 0;
            // Try to extract the author name from the comment DOM (preferred) otherwise fall back to link text/attributes
            var authorName = '';
            try {
                var parentForName = link.closest('[id^="comment-"]') || link.closest('li') || link.closest('.comment');
                if (parentForName) {
                    var fn = parentForName.querySelector('.comment-author-info .fn, .comment-author .fn, .fn');
                    if (fn) authorName = (fn.textContent || fn.innerText || '').trim();
                }
            } catch(e) {}
            if (!authorName) {
                authorName = link.getAttribute('data-commenter') || link.getAttribute('data-comment-author') || (link.textContent || '').trim() || '';
            }

            var form = document.getElementById('commentform') || document.querySelector('#respond form');
            if (!form) return;

            // Ensure hidden parent input exists
            var parentInput = form.querySelector('input[name="comment_parent"]');
            if (!parentInput) {
                parentInput = document.createElement('input');
                parentInput.type = 'hidden';
                parentInput.name = 'comment_parent';
                parentInput.value = '0';
                form.appendChild(parentInput);
            }
            parentInput.value = '' + parentId;

            // Show a small note in #respond so user sees they're replying, with cancel
            var respond = document.getElementById('respond');
            var note = document.getElementById('atareao-replying-note');
            if (!note) {
                note = document.createElement('div');
                note.id = 'atareao-replying-note';
                note.className = 'atareao-replying-note';
            }
            note.innerHTML = 'Respondiendo a ' + (authorName ? authorName : '') + ' <button type="button" id="atareao-cancel-reply">Cancelar</button>';
            if (respond) {
                // place the note at the top of the respond area
                respond.insertBefore(note, respond.firstChild);
            }

            // focus textarea
            var ta = form.querySelector('textarea[name="comment"]');
            if (ta) ta.focus();

            // cancel handler
            var cancel = document.getElementById('atareao-cancel-reply');
            if (cancel) {
                cancel.addEventListener('click', function(){
                    parentInput.value = '0';
                    if (note && note.parentNode) note.parentNode.removeChild(note);
                });
            }
        }, true);

        form.addEventListener('submit', function(e){
            e.preventDefault();

            var submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
            if (submitBtn) submitBtn.disabled = true;
            messageEl.textContent = '';
            messageEl.className = '';

            var data = new FormData(form);
            // Ensure comment_parent is always sent (may be set by our reply intercept)
            var parentInput = form.querySelector('input[name="comment_parent"]');
            var parentVal = parentInput ? parentInput.value : (form.dataset && form.dataset.replyParent ? form.dataset.replyParent : '0');
            data.set('comment_parent', parentVal || '0');
            data.append('action', 'atareao_submit_comment');
            data.append('nonce', (window.atareao_ajax && window.atareao_ajax.nonce) ? window.atareao_ajax.nonce : '');

            fetch((window.atareao_ajax && window.atareao_ajax.ajax_url) ? window.atareao_ajax.ajax_url : '/wp-admin/admin-ajax.php', {
                method: 'POST',
                credentials: 'same-origin',
                body: data
            }).then(function(res){
                return res.json();
            }).then(function(json){
                if (json.success) {
                    messageEl.textContent = (json.data && json.data.message) ? json.data.message : 'Comentario enviado.';
                    messageEl.className = 'atareao-comment-success';
                    // clear comment textarea
                    var ta = form.querySelector('textarea[name="comment"]');
                    if (ta) ta.value = '';

                    // If server returned full rendered HTML, insert it into the correct place
                    if (json.data && json.data.comment_html) {
                        var commentsDiv = document.getElementById('comments');
                        var respond = document.getElementById('respond');
                        var list = commentsDiv ? commentsDiv.querySelector('.comment-list') : document.querySelector('.comment-list');
                        // If list exists but is after the respond form, treat as not found
                        try {
                            if (list && respond && (list.compareDocumentPosition(respond) & Node.DOCUMENT_POSITION_FOLLOWING)) {
                                list = null;
                            }
                        } catch(e) {}
                        if (list) {
                            // If this comment is a reply to another comment, insert into that parent's children list
                            var parentId = (json.data && typeof json.data.parent !== 'undefined') ? parseInt(json.data.parent, 10) : 0;
                            if (parentId && parentId > 0) {
                                var parentLi = document.getElementById('comment-' + parentId) || document.querySelector('[data-comment-id="' + parentId + '"]') || document.querySelector('[data-commentid="' + parentId + '"]');
                                if (parentLi) {
                                    var children = parentLi.querySelector('ol.children');
                                    if (!children) {
                                        children = document.createElement('ol');
                                        children.className = 'children';
                                        // Append children list inside the parent li
                                        parentLi.appendChild(children);
                                    }
                                    // Parse and append node to avoid malformed insertion
                                    var temp = document.createElement('div');
                                    temp.innerHTML = json.data.comment_html;
                                    var node = temp.querySelector('[id^="comment-"]') || temp.firstElementChild;
                                    if (node) {
                                        children.appendChild(node);
                                        // If node wasn't actually appended inside parent, move it
                                        if (!parentLi.contains(node)) {
                                            children.appendChild(node);
                                        }
                                    }
                                    try {
                                        var appended = node && node.id ? document.getElementById(node.id) : null;
                                        if (appended) appended.scrollIntoView({behavior:'smooth', block:'center'});
                                    } catch(e) {}
                                } else {
                                    // Parent not found, fallback to appending to main list
                                    var temp = document.createElement('div');
                                    temp.innerHTML = json.data.comment_html;
                                    var node = temp.querySelector('[id^="comment-"]') || temp.firstElementChild;
                                    if (node) list.appendChild(node);
                                }
                            } else {
                                // Insert into the existing top-level list
                                list.insertAdjacentHTML('beforeend', json.data.comment_html);
                            }
                            // Update comments counter if present
                            try {
                                var countEl = document.querySelector('.comments-count');
                                if (countEl) {
                                    var n = parseInt(countEl.textContent.replace(/[^0-9]/g,''), 10) || 0;
                                    countEl.textContent = n + 1;
                                }
                            } catch(e) {}
                        } else {
                            // No existing list found: insert the HTML before the respond form
                            var respond = document.getElementById('respond');
                            if (respond) {
                                // Create a list before the respond form and append the parsed node
                                var wrapper = document.createElement('ol');
                                wrapper.className = 'comment-list';
                                var temp = document.createElement('div');
                                temp.innerHTML = json.data.comment_html;
                                var node = temp.querySelector('[id^="comment-"]') || temp.firstElementChild;
                                if (node) wrapper.appendChild(node);
                                respond.parentNode.insertBefore(wrapper, respond);
                                try { if (node && node.id) document.getElementById(node.id).scrollIntoView({behavior:'smooth', block:'center'}); } catch(e) {}
                                try {
                                    var countEl = document.querySelector('.comments-count');
                                    if (countEl) {
                                        var n = parseInt(countEl.textContent.replace(/[^0-9]/g,''), 10) || 0;
                                        countEl.textContent = n + 1;
                                    }
                                } catch(e) {}
                            }
                        }
                    }
                } else {
                    messageEl.textContent = (json.data && json.data.message) ? json.data.message : 'Error enviando comentario.';
                    messageEl.className = 'atareao-comment-error';
                }

                // Update captcha label and hidden time if provided
                if (json.data && typeof json.data.new_a !== 'undefined' && typeof json.data.new_b !== 'undefined') {
                    var label = document.querySelector('label[for="atareao_comment_captcha"]');
                    if (label) {
                        label.textContent = '¿Cuánto es ' + json.data.new_a + ' + ' + json.data.new_b + '? ';
                    }
                    var timeInput = form.querySelector('input[name="atareao_comment_form_time"]');
                    if (timeInput && json.data.new_time) {
                        timeInput.value = json.data.new_time;
                    }
                    // clear captcha input
                    var ci = form.querySelector('input[name="atareao_comment_captcha"]');
                    if (ci) ci.value = '';
                }

            }).catch(function(err){
                messageEl.textContent = 'Error de red. Inténtalo de nuevo.';
                messageEl.className = 'atareao-comment-error';
            }).finally(function(){
                if (submitBtn) submitBtn.disabled = false;
                // auto-hide after 6s
                setTimeout(function(){ if (messageEl) { messageEl.style.display = 'none'; } }, 6000);
            });
        });
    });
})();
