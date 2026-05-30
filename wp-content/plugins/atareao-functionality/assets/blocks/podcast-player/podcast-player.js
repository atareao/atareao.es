(function () {
    'use strict';

    function formatTime(seconds)
    {
        if (isNaN(seconds)) {
            return '0:00';
        }
        const mins = Math.floor(seconds / 60);
        const secs = Math.floor(seconds % 60);
        return mins + ':' + (secs < 10 ? '0' : '') + secs;
    }

    function initPlayer(container)
    {
        const playerId = container.getAttribute('data-player-id');
        const player = document.getElementById(playerId);
        if (!player) {
            return;
        }

        const playPauseBtn = container.querySelector('.podcast-play-pause');
        const backwardBtn = container.querySelector('.podcast-backward');
        const forwardBtn = container.querySelector('.podcast-forward');
        const progressBar = container.querySelector('.podcast-progress-filled');
        const progressContainer = container.querySelector('.podcast-progress-bar');
        const currentTimeEl = container.querySelector('.podcast-current-time');
        const durationEl = container.querySelector('.podcast-duration');
        const muteBtn = container.querySelector('.podcast-mute');
        const volumeSlider = container.querySelector('.podcast-volume');
        const speedSelect = container.querySelector('.podcast-speed');

        playPauseBtn.addEventListener('click', function () {
            if (player.paused) {
                player.play();
                playPauseBtn.querySelector('.dashicons').classList.remove('dashicons-controls-play');
                playPauseBtn.querySelector('.dashicons').classList.add('dashicons-controls-pause');
            } else {
                player.pause();
                playPauseBtn.querySelector('.dashicons').classList.remove('dashicons-controls-pause');
                playPauseBtn.querySelector('.dashicons').classList.add('dashicons-controls-play');
            }
        });

        backwardBtn.addEventListener('click', function () {
            player.currentTime = Math.max(0, player.currentTime - 30);
            this.style.transform = 'scale(0.9)';
            setTimeout(() => { this.style.transform = 'scale(1)'; }, 150);
        });

        forwardBtn.addEventListener('click', function () {
            player.currentTime = Math.min(player.duration, player.currentTime + 30);
            this.style.transform = 'scale(0.9)';
            setTimeout(() => { this.style.transform = 'scale(1)'; }, 150);
        });

        document.addEventListener('keydown', function (e) {
            if (!container.querySelector('.podcast-audio-element:focus') &&
                !document.activeElement.classList.contains('podcast-speed') &&
                !document.activeElement.classList.contains('podcast-volume')) {
                if (e.code === 'Space' && e.target === document.body) {
                    e.preventDefault();
                    playPauseBtn.click();
                } else if (e.code === 'ArrowLeft') {
                    e.preventDefault();
                    backwardBtn.click();
                } else if (e.code === 'ArrowRight') {
                    e.preventDefault();
                    forwardBtn.click();
                }
            }
        });

        player.addEventListener('timeupdate', function () {
            if (player.duration) {
                const percent = (player.currentTime / player.duration) * 100;
                progressBar.style.width = percent + '%';
                currentTimeEl.textContent = formatTime(player.currentTime);
            }
        });

        player.addEventListener('loadedmetadata', function () {
            durationEl.textContent = formatTime(player.duration);
        });

        progressContainer.addEventListener('click', function (e) {
            const rect = progressContainer.getBoundingClientRect();
            const percent = (e.clientX - rect.left) / rect.width;
            player.currentTime = percent * player.duration;
        });

        let isSeeking = false;
        progressContainer.addEventListener('touchstart', function () {
            isSeeking = true; });
        progressContainer.addEventListener('touchmove', function (e) {
            if (isSeeking) {
                const rect = progressContainer.getBoundingClientRect();
                const touch = e.touches[0];
                const percent = (touch.clientX - rect.left) / rect.width;
                player.currentTime = Math.max(0, Math.min(1, percent)) * player.duration;
            }
        });
        progressContainer.addEventListener('touchend', function () {
            isSeeking = false; });

        volumeSlider.addEventListener('input', function () {
            player.volume = this.value / 100;
            player.muted = false;
            updateVolumeIcon();
        });

        let volumeOpenedByClick = false;

        document.addEventListener('click', function (e) {
            const volumeControl = container.querySelector('.podcast-volume-control');
            if (!volumeControl.contains(e.target) && volumeControl.classList.contains('show-volume') && volumeOpenedByClick) {
                volumeControl.classList.remove('show-volume');
                volumeOpenedByClick = false;
            }
        });

        muteBtn.addEventListener('click', function () {
            const volumeControl = container.querySelector('.podcast-volume-control');
            if (volumeControl.classList.contains('show-volume')) {
                volumeOpenedByClick = true;
            }
        });

        muteBtn.addEventListener('click', function (e) {
            if (e.button !== 0 || e.ctrlKey || e.shiftKey) {
                player.muted = !player.muted;
                updateVolumeIcon();
            } else {
                const volumeControl = container.querySelector('.podcast-volume-control');
                volumeControl.classList.toggle('show-volume');
            }
        });

        function updateVolumeIcon()
        {
            const icon = muteBtn.querySelector('.dashicons');
            icon.classList.remove('dashicons-controls-volumeon', 'dashicons-controls-volumeoff');
            if (player.muted || player.volume === 0) {
                icon.classList.add('dashicons-controls-volumeoff');
            } else {
                icon.classList.add('dashicons-controls-volumeon');
            }
        }

        speedSelect.addEventListener('change', function () {
            player.playbackRate = parseFloat(this.value);
        });

        player.addEventListener('ended', function () {
            playPauseBtn.querySelector('.dashicons').classList.remove('dashicons-controls-pause');
            playPauseBtn.querySelector('.dashicons').classList.add('dashicons-controls-play');
            progressBar.style.width = '0%';
            player.currentTime = 0;
        });
    }

    document.querySelectorAll('.atareao-podcast-player').forEach(initPlayer);
})();