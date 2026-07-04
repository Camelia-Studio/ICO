class Lightbox {
    #items = [];
    #current = -1;
    #timer = null;
    #interval = 5;
    #isOpen = false;
    #sheetOpen = false;
    #touchStartX = 0;
    #touchStartY = 0;

    // DOM refs
    #el;
    #backdrop;
    #img;
    #video;
    #counter;
    #intervalInput;
    #sheetEl;

    // NodeLists (plusieurs éléments par classe)
    #slideShowBtns;
    #downloadLinks;
    #shareBtns;
    #sourceLinks;

    constructor(el) {
        this.#el          = el;
        this.#interval    = parseInt(el.dataset.slideshowInterval, 10) || 5;
        this.#backdrop    = el.querySelector('.lightbox-backdrop');
        this.#img         = el.querySelector('.lightbox-img');
        this.#video       = el.querySelector('.lightbox-video');
        this.#counter     = el.querySelector('.lightbox-counter');
        this.#intervalInput = el.querySelector('.lb-slideshow-interval');
        this.#sheetEl     = el.querySelector('.lightbox-sheet');
        this.#slideShowBtns = el.querySelectorAll('.lb-slideshow-btn');
        this.#downloadLinks = el.querySelectorAll('.lb-download');
        this.#shareBtns   = el.querySelectorAll('.lb-share-btn');
        this.#sourceLinks = el.querySelectorAll('.lb-source-btn');

        if (this.#intervalInput) {
            this.#intervalInput.value = String(this.#interval);
        }

        this.#bindEvents();
    }

    init(triggers) {
        this.#items = Array.from(triggers).map(t => ({
            src:      t.dataset.src,
            type:     t.dataset.type || 'image',
            mime:     t.dataset.mime || null,
            downloadName: t.dataset.downloadName || '',
            shareUrl: t.dataset.shareUrl || null,
        }));

        triggers.forEach((trigger, i) => {
            trigger.addEventListener('click', e => {
                e.preventDefault();
                this.open(i);
            });
        });
    }

    open(index) {
        this.#isOpen = true;
        this.#el.setAttribute('aria-hidden', 'false');
        this.#el.classList.add('is-open');
        document.body.style.overflow = 'hidden';
        this.goTo(index);
    }

    close() {
        this.stopSlideshow();
        this.#pauseVideo();
        this.#isOpen = false;
        this.#el.setAttribute('aria-hidden', 'true');
        this.#el.classList.remove('is-open');
        document.body.style.overflow = '';
        this.#closeSheet();
    }

    goTo(index) {
        this.#pauseVideo();
        const count = this.#items.length;
        this.#current = ((index % count) + count) % count;
        this.#loadMedia(this.#items[this.#current]);
        this.#updateUI();
        this.#closeSheet();
    }

    prev() {
        this.stopSlideshow();
        this.goTo(this.#current - 1);
    }

    next() {
        this.goTo(this.#current + 1);
    }

    startSlideshow() {
        if (this.#timer) return;
        const ms = (parseInt(this.#intervalInput?.value ?? '', 10) || this.#interval) * 1000;
        this.#timer = setInterval(() => this.next(), ms);
        this.#slideShowBtns.forEach(b => b.classList.add('is-playing'));
    }

    stopSlideshow() {
        if (!this.#timer) return;
        clearInterval(this.#timer);
        this.#timer = null;
        this.#slideShowBtns.forEach(b => b.classList.remove('is-playing'));
    }

    toggleSlideshow() {
        this.#timer ? this.stopSlideshow() : this.startSlideshow();
    }

    enterFullscreen() {
        if (document.fullscreenElement) {
            document.exitFullscreen?.().catch(() => {});
        } else {
            this.#el.requestFullscreen?.().catch(() => {});
        }
    }

    // -------------------------------------------------------------------------
    // Privé
    // -------------------------------------------------------------------------

    #loadMedia(item) {
        if (item.type === 'video') {
            this.#img.style.display  = 'none';
            this.#video.style.display = 'block';
            this.#video.src = item.src;
            this.#video.play().catch(() => {});
        } else {
            this.#video.style.display = 'none';
            this.#img.style.display  = 'block';
            this.#img.src = item.src;
        }
    }

    #pauseVideo() {
        if (!this.#video.paused) this.#video.pause();
        this.#video.src = '';
    }

    #updateUI() {
        const item         = this.#items[this.#current];
        const isVideo      = item.type === 'video';
        const allowDl      = this.#el.dataset.allowDownload === '1';
        const allowShare   = this.#el.dataset.allowShare    === '1';
        const allowSource  = this.#el.dataset.allowSource   === '1';

        this.#counter.textContent = `${this.#current + 1} / ${this.#items.length}`;

        // Télécharger
        this.#downloadLinks.forEach(el => {
            el.href = item.src;
            el.download = item.downloadName || '';
            el.style.display = allowDl ? '' : 'none';
        });

        // Partager
        const shareVisible = allowShare && !!item.shareUrl;
        this.#shareBtns.forEach(el => {
            el.style.display = shareVisible ? '' : 'none';
        });

        // Source — images uniquement
        const sourceVisible = allowSource && !isVideo;
        this.#sourceLinks.forEach(el => {
            el.href = `https://saucenao.com/search.php?url=${encodeURIComponent(item.src)}`;
            el.style.display = sourceVisible ? '' : 'none';
        });

        // Diaporama — masqué pour les vidéos
        this.#slideShowBtns.forEach(el => {
            el.style.display = isVideo ? 'none' : '';
        });
        if (this.#intervalInput) {
            this.#intervalInput.style.display = isVideo ? 'none' : '';
        }
    }

    #openSheet() {
        this.#sheetOpen = true;
        this.#sheetEl.classList.add('is-open');
    }

    #closeSheet() {
        this.#sheetOpen = false;
        this.#sheetEl.classList.remove('is-open');
    }

    #openSharePage() {
        const item = this.#items[this.#current];
        if (item.shareUrl) window.open(item.shareUrl, '_blank');
    }

    #bindEvents() {
        // Clic sur le fond
        this.#backdrop.addEventListener('click', () => this.close());

        // Boutons data-action
        this.#el.querySelectorAll('[data-action]').forEach(btn => {
            btn.addEventListener('click', e => {
                e.stopPropagation();
                switch (btn.dataset.action) {
                    case 'close':      this.close(); break;
                    case 'fullscreen': this.enterFullscreen(); break;
                    case 'slideshow':  this.toggleSlideshow(); break;
                    case 'prev':       this.prev(); break;
                    case 'next':       this.next(); break;
                    case 'share':      this.#openSharePage(); break;
                }
            });
        });

        // Touch — swipe et tap
        this.#el.addEventListener('touchstart', e => {
            this.#touchStartX = e.changedTouches[0].clientX;
            this.#touchStartY = e.changedTouches[0].clientY;
        }, { passive: true });

        this.#el.addEventListener('touchend', e => {
            if (e.target.closest('.lightbox-video, .lightbox-sheet, button, a, input')) return;

            const dx = e.changedTouches[0].clientX - this.#touchStartX;
            const dy = e.changedTouches[0].clientY - this.#touchStartY;

            if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy)) {
                this.#closeSheet();
                dx < 0 ? this.next() : this.prev();
                this.stopSlideshow();
                return;
            }

            if (Math.abs(dx) < 10 && Math.abs(dy) < 10) {
                this.#sheetOpen ? this.#closeSheet() : this.#openSheet();
            }
        });

        // Plein écran — mise à jour de l'icône
        document.addEventListener('fullscreenchange', () => {
            this.#el.querySelector('.lb-fullscreen-btn')
                ?.classList.toggle('is-fullscreen', !!document.fullscreenElement);
        });

        // Clavier
        document.addEventListener('keydown', e => {
            if (!this.#isOpen) return;
            if (e.key === 'ArrowLeft')  this.prev();
            if (e.key === 'ArrowRight') this.next();
            if (e.key === 'Escape')     this.close();
        });
    }
}

// ─── Init ────────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    const el = document.getElementById('lightbox');
    if (!el) return;

    const lb       = new Lightbox(el);
    const triggers = document.querySelectorAll('.gallery-lightbox-trigger');
    lb.init(triggers);
});
