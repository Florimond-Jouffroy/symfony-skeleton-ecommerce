const ICON_IMG = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>';

const ALIGN_ICONS = {
    left:   '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="21" y1="6" x2="3" y2="6"/><line x1="15" y1="12" x2="3" y2="12"/><line x1="17" y1="18" x2="3" y2="18"/></svg>',
    center: '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="21" y1="6" x2="3" y2="6"/><line x1="18" y1="12" x2="6" y2="12"/><line x1="21" y1="18" x2="3" y2="18"/></svg>',
    right:  '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="21" y1="6" x2="3" y2="6"/><line x1="21" y1="12" x2="9" y2="12"/><line x1="21" y1="18" x2="7" y2="18"/></svg>',
};

const ALIGN_LABELS = { left: 'Gauche', center: 'Centre', right: 'Droite' };

export default class EditorImageTool {
    static get toolbox() {
        return { title: 'Image', icon: ICON_IMG };
    }

    static get isReadOnlySupported() {
        return true;
    }

    constructor({ data, config, readOnly }) {
        this._data = {
            file:    data.file    ?? { url: '' },
            caption: data.caption ?? '',
            width:   data.width   ?? '100',
            align:   data.align   ?? 'center',
        };
        this._config   = config ?? {};
        this._readOnly = readOnly;

        this._wrapper   = null;
        this._img       = null;
        this._slider    = null;
        this._widthLbl  = null;
        this._captionEl = null;
        this._alignBtns = {};
    }

    render() {
        this._wrapper = document.createElement('div');
        this._wrapper.style.padding = '4px 0';

        if (this._data.file.url) {
            this._buildImageView();
        } else {
            this._buildUploadView();
        }

        return this._wrapper;
    }

    _buildUploadView() {
        const btn = document.createElement('div');
        Object.assign(btn.style, {
            display: 'flex', alignItems: 'center', gap: '10px',
            padding: '16px', border: '2px dashed #d1d5db',
            borderRadius: '8px', cursor: 'pointer',
            color: '#6b7280', fontSize: '14px',
        });
        btn.innerHTML = `${ICON_IMG} <span>Cliquer pour uploader une image</span>`;

        btn.addEventListener('click', () => {
            const input = document.createElement('input');
            input.type   = 'file';
            input.accept = 'image/*';
            input.onchange = async (e) => {
                const file = e.target.files[0];
                if (!file || !this._config.uploader?.uploadByFile) return;

                btn.querySelector('span').textContent = 'Upload en cours…';
                const result = await this._config.uploader.uploadByFile(file);

                if (result.success && result.file?.url) {
                    this._data.file = result.file;
                    this._wrapper.innerHTML = '';
                    this._buildImageView();
                } else {
                    btn.querySelector('span').textContent = 'Échec — réessayer';
                }
            };
            input.click();
        });

        this._wrapper.appendChild(btn);
    }

    _applyAlignment() {
        if (!this._img) return;
        const { align, width } = this._data;
        this._img.style.width       = `${width}%`;
        this._img.style.display     = 'block';
        this._img.style.marginLeft  = align === 'right'  ? 'auto' : (align === 'center' ? 'auto' : '0');
        this._img.style.marginRight = align === 'left'   ? 'auto' : (align === 'center' ? 'auto' : '0');
    }

    _buildImageView() {
        // ── Image ──────────────────────────────────────────────────────────
        this._img = document.createElement('img');
        this._img.src = this._data.file.url;
        this._img.alt = this._data.caption ? this._data.caption.replace(/<[^>]*>/g, '') : '';
        Object.assign(this._img.style, {
            maxWidth: '100%', borderRadius: '6px',
            transition: 'width 0.1s ease, margin 0.1s ease',
        });
        this._applyAlignment();
        this._wrapper.appendChild(this._img);

        if (!this._readOnly) {
            // ── Contrôles (largeur + alignement) ──────────────────────────
            const controls = document.createElement('div');
            controls.style.cssText = 'display:flex;align-items:center;gap:10px;margin-top:10px;flex-wrap:wrap;';

            // Slider largeur
            const sliderLbl = document.createElement('span');
            sliderLbl.style.cssText = 'font-size:12px;color:#6b7280;white-space:nowrap;';
            sliderLbl.textContent = 'Largeur :';

            this._slider = document.createElement('input');
            this._slider.type  = 'range';
            this._slider.min   = '10';
            this._slider.max   = '100';
            this._slider.step  = '5';
            this._slider.value = this._data.width;
            this._slider.style.cssText = 'flex:1;min-width:80px;cursor:pointer;accent-color:#3b82f6;';

            this._widthLbl = document.createElement('span');
            this._widthLbl.style.cssText = 'font-size:12px;font-weight:600;color:#374151;min-width:36px;text-align:right;';
            this._widthLbl.textContent = `${this._data.width}%`;

            this._slider.addEventListener('input', () => {
                this._data.width = this._slider.value;
                this._widthLbl.textContent = `${this._slider.value}%`;
                this._applyAlignment();
            });
            this._slider.addEventListener('keydown', (e) => e.stopPropagation());

            // Séparateur
            const sep = document.createElement('div');
            sep.style.cssText = 'width:1px;height:20px;background:#e5e7eb;flex-shrink:0;';

            // Boutons alignement
            const alignGroup = document.createElement('div');
            alignGroup.style.cssText = 'display:flex;gap:2px;flex-shrink:0;';

            for (const dir of ['left', 'center', 'right']) {
                const b = document.createElement('button');
                b.type      = 'button';
                b.title     = ALIGN_LABELS[dir];
                b.innerHTML = ALIGN_ICONS[dir];
                b.dataset.align = dir;
                Object.assign(b.style, {
                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                    width: '28px', height: '28px', border: '1px solid #e5e7eb',
                    borderRadius: '6px', cursor: 'pointer', background: 'transparent',
                    color: '#6b7280', transition: 'all 0.1s',
                });

                b.addEventListener('click', () => {
                    this._data.align = dir;
                    this._applyAlignment();
                    this._updateAlignButtons();
                });

                this._alignBtns[dir] = b;
                alignGroup.appendChild(b);
            }

            controls.append(sliderLbl, this._slider, this._widthLbl, sep, alignGroup);
            this._wrapper.appendChild(controls);
            this._updateAlignButtons();
        }

        // ── Légende ────────────────────────────────────────────────────────
        this._captionEl = document.createElement('div');
        Object.assign(this._captionEl.style, {
            marginTop: '8px', fontSize: '13px',
            textAlign: 'center', outline: 'none', minHeight: '20px',
            color: this._data.caption ? '#374151' : '#9ca3af',
        });

        if (!this._readOnly) {
            this._captionEl.contentEditable = 'true';
            this._captionEl.setAttribute('placeholder', 'Légende (optionnel)');
            if (this._data.caption) this._captionEl.innerHTML = this._data.caption;

            this._captionEl.addEventListener('focus', () => {
                this._captionEl.style.color = '#374151';
            });
            this._captionEl.addEventListener('blur', () => {
                if (!this._captionEl.textContent.trim())
                    this._captionEl.style.color = '#9ca3af';
            });
        } else {
            this._captionEl.innerHTML = this._data.caption;
        }

        this._wrapper.appendChild(this._captionEl);
    }

    _updateAlignButtons() {
        for (const [dir, btn] of Object.entries(this._alignBtns)) {
            const active = dir === this._data.align;
            btn.style.background   = active ? '#eff6ff' : 'transparent';
            btn.style.borderColor  = active ? '#3b82f6' : '#e5e7eb';
            btn.style.color        = active ? '#2563eb' : '#6b7280';
        }
    }

    save() {
        return {
            file:    this._data.file,
            caption: this._captionEl ? this._captionEl.innerHTML : this._data.caption,
            width:   this._slider    ? this._slider.value        : this._data.width,
            align:   this._data.align,
        };
    }

    validate(data) {
        return !!(data.file?.url);
    }
}
