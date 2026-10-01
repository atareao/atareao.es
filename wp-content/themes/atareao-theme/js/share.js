/**
 * Native Web Share API with clipboard fallback
 *
 * Uses navigator.share() when available (mobile browsers, Chrome desktop 89+).
 * Falls back to copying the URL to clipboard + showing a toast notification.
 *
 * @since 2.0.0
 */
(function () {
    'use strict';

    /**
     * Show a brief toast notification at the bottom of the screen.
     * @param {string} message - Text to display.
     */
    function showToast(message) {
        var toast = document.createElement('div');
        toast.className = 'share-toast';
        toast.textContent = message;
        document.body.appendChild(toast);

        requestAnimationFrame(function () {
            toast.classList.add('share-toast--show');
        });

        setTimeout(function () {
            toast.classList.remove('share-toast--show');
            setTimeout(function () {
                toast.remove();
            }, 300);
        }, 2000);
    }

    /**
     * Copy text to clipboard, with fallback for older browsers.
     * @param {string} text - Text to copy.
     * @returns {Promise<boolean>}
     */
    function copyToClipboard(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text).then(function () {
                return true;
            }).catch(function () {
                return fallbackCopy(text);
            });
        }
        return fallbackCopy(text);
    }

    /**
     * Fallback copy using legacy document.execCommand('copy').
     * @param {string} text
     * @returns {boolean}
     */
    function fallbackCopy(text) {
        var textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        textarea.style.pointerEvents = 'none';
        textarea.style.left = '-9999px';
        document.body.appendChild(textarea);
        textarea.select();
        try {
            return document.execCommand('copy');
        } catch (e) {
            return false;
        } finally {
            document.body.removeChild(textarea);
        }
    }

    // Delegated click handler for all .share-btn-native buttons
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.share-btn-native');
        if (!btn) {
            return;
        }
        e.preventDefault();

        var url = btn.getAttribute('data-url');
        var title = btn.getAttribute('data-title') || document.title;

        if (!url) {
            return;
        }

        // Web Share API — native OS share sheet
        if (navigator.share) {
            navigator.share({
                title: title,
                url: url
            }).catch(function () {
                // User cancelled — do nothing
            });
            return;
        }

        // Fallback: copy URL to clipboard
        copyToClipboard(url).then(function (ok) {
            if (ok) {
                showToast('Enlace copiado');
            }
        });
    });
})();