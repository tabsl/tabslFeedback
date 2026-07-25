/*
 * tabslFeedback — Frontend-Widget und Paste-Logik des Backend-Formulars.
 *
 * Bewusst ohne Framework: das Modul soll in jedem OXID-Theme laufen, auch in
 * einem, das weder jQuery noch Bootstrap mitbringt.
 *
 * Screenshots werden NIE als multipart/form-data gesendet, sondern als
 * base64-Daten-URL in einem normalen POST-Feld — Multipart-Uploads an index.php
 * sind in diesem Umfeld bereits an einer WAF gescheitert (planning.md §7 Nr. 2).
 */
(function () {
    'use strict';

    var FRONTEND_CONFIG_ID = 'tabslfeedback-config';
    var ADMIN_FORM_ID = 'tabslfeedback-admin-form';

    /* ------------------------------------------------------------------ *
     * Hilfsfunktionen
     * ------------------------------------------------------------------ */

    function parseJsonAttribute(element, attribute, fallback) {
        var raw = element.getAttribute(attribute);

        if (!raw) {
            return fallback;
        }

        try {
            return JSON.parse(raw) || fallback;
        } catch (error) {
            return fallback;
        }
    }

    function createElement(tag, className, textContent) {
        var element = document.createElement(tag);

        if (className) {
            element.className = className;
        }

        if (textContent !== undefined && textContent !== null) {
            element.textContent = textContent;
        }

        return element;
    }

    function isPlausibleEmail(value) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(value);
    }

    /**
     * Die vier Angaben aus planning.md §8. Mehr wird nicht erhoben — insbesondere
     * keine Konsolenmeldungen und kein Warenkorb-Kontext.
     */
    function collectMeta() {
        var meta = {
            url: window.location.href,
            referrer: document.referrer || ''
        };

        if (window.innerWidth && window.innerHeight) {
            meta.viewport = { w: window.innerWidth, h: window.innerHeight };
        }

        if (window.screen && window.screen.width && window.screen.height) {
            meta.screen = { w: window.screen.width, h: window.screen.height };
        }

        return JSON.stringify(meta);
    }

    /* ------------------------------------------------------------------ *
     * Screenshot-Verwaltung (Zwischenablage, Vorschau, Grenzwerte)
     * ------------------------------------------------------------------ */

    function ImageStore(limits, texts, onError) {
        this.limits = limits;
        this.texts = texts;
        this.onError = onError;
        this.items = [];

        // Das Einlesen läuft asynchron. Enthält ein Einfügevorgang mehrere
        // Bilder, wären sonst alle durch die Prüfung, bevor das erste in items
        // liegt — die Anzahl- und Summengrenze griffe dann zu spät.
        this.pending = [];
    }

    ImageStore.prototype.count = function () {
        return this.items.length + this.pending.length;
    };

    ImageStore.prototype.totalBytes = function () {
        var total = 0;

        this.items.forEach(function (item) {
            total += item.size;
        });

        this.pending.forEach(function (size) {
            total += size;
        });

        return total;
    };

    /**
     * Prüft dieselben Grenzwerte wie der Server. Diese Prüfung ist reine
     * Bequemlichkeit — sie erspart den Roundtrip, ersetzt aber nichts.
     */
    ImageStore.prototype.canAccept = function (file) {
        if (this.count() >= this.limits.maxImages) {
            this.onError(this.texts.errorTooManyImages);
            return false;
        }

        if (this.limits.allowedMimeTypes.indexOf(file.type) === -1) {
            this.onError(this.texts.errorImageType);
            return false;
        }

        if (file.size > this.limits.maxImageBytes) {
            this.onError(this.texts.errorImageTooLarge);
            return false;
        }

        if (this.totalBytes() + file.size > this.limits.maxTotalBytes) {
            this.onError(this.texts.errorImagesTooLarge);
            return false;
        }

        return true;
    };

    ImageStore.prototype.add = function (file, onAdded) {
        if (!this.canAccept(file)) {
            return;
        }

        var store = this;
        var reader = new FileReader();

        this.pending.push(file.size);

        function releasePending() {
            var position = store.pending.indexOf(file.size);

            if (position !== -1) {
                store.pending.splice(position, 1);
            }
        }

        reader.onload = function () {
            releasePending();
            store.items.push({ dataUrl: String(reader.result), size: file.size });
            onAdded();
        };

        reader.onerror = function () {
            releasePending();
            store.onError(store.texts.errorGeneric);
        };

        reader.readAsDataURL(file);
    };

    ImageStore.prototype.remove = function (index) {
        this.items.splice(index, 1);
    };

    ImageStore.prototype.dataUrls = function () {
        return this.items.map(function (item) {
            return item.dataUrl;
        });
    };

    ImageStore.prototype.clear = function () {
        this.items = [];
        this.pending = [];
    };

    function bindPaste(target, store, onChange) {
        target.addEventListener('paste', function (event) {
            var clipboard = event.clipboardData || window.clipboardData;

            if (!clipboard || !clipboard.items) {
                return;
            }

            var handled = false;

            Array.prototype.forEach.call(clipboard.items, function (item) {
                if (item.kind !== 'file' || item.type.indexOf('image/') !== 0) {
                    return;
                }

                var file = item.getAsFile();

                if (file) {
                    handled = true;
                    store.add(file, onChange);
                }
            });

            if (handled) {
                // Sonst fügt der Browser zusätzlich einen Dateinamen als Text ein.
                event.preventDefault();
            }
        });
    }

    function renderPreviews(list, store, texts, onChange) {
        list.innerHTML = '';

        store.items.forEach(function (item, index) {
            var entry = createElement('li', 'tabslfeedback-preview');
            var image = createElement('img', 'tabslfeedback-preview__image');
            image.src = item.dataUrl;
            image.alt = '';

            var remove = createElement('button', 'tabslfeedback-preview__remove', '×');
            remove.type = 'button';
            remove.setAttribute('aria-label', texts.removeImage);
            remove.addEventListener('click', function () {
                store.remove(index);
                onChange();
            });

            entry.appendChild(image);
            entry.appendChild(remove);
            list.appendChild(entry);
        });
    }

    /* ------------------------------------------------------------------ *
     * Gemeinsame Eingabeprüfung vor dem Absenden
     * ------------------------------------------------------------------ */

    function validateFields(values, limits, texts) {
        if (!values.message.trim()) {
            return texts.errorMessageRequired;
        }

        if (values.message.length > limits.maxMessageLength) {
            return texts.errorMessageTooLong;
        }

        if (values.name.length > limits.maxContactLength || values.email.length > limits.maxContactLength) {
            return texts.errorContactTooLong;
        }

        if (values.email && !isPlausibleEmail(values.email)) {
            return texts.errorEmailInvalid;
        }

        return '';
    }

    /* ------------------------------------------------------------------ *
     * Frontend: Button + Dialog
     * ------------------------------------------------------------------ */

    function FrontendWidget(config) {
        this.config = config;
        this.limits = config.limits;
        this.texts = config.texts;
        this.store = null;

        // Kennung des selbst gerenderten Turnstile-Widgets. Sie ist der einzige
        // verlässliche Weg, den Token später zurückzusetzen.
        this.turnstileWidgetId = null;
        this.turnstileAttempts = 0;

        // Ein Request läuft gerade.
        this.sending = false;

        // Erfolgreich abgesendet. Der Absenden-Knopf bleibt danach gesperrt, bis
        // wieder etwas eingegeben wird — sonst erzeugt ein zweiter Klick auf den
        // scheinbar noch bedienbaren Knopf ein zweites Ticket zur selben Meldung.
        this.submitted = false;
    }

    FrontendWidget.prototype.init = function () {
        var widget = createElement('div', 'tabslfeedback-widget');

        this.trigger = this.buildTrigger();
        this.overlay = this.buildDialog();

        widget.appendChild(this.trigger);
        widget.appendChild(this.overlay);
        document.body.appendChild(widget);

        var self = this;

        this.store = new ImageStore(this.limits, this.texts, function (message) {
            self.showMessage(message, false);
        });

        bindPaste(this.dialog, this.store, function () {
            self.refreshPreviews();
        });

        this.trigger.addEventListener('click', function () {
            self.open();
        });

        this.closeButton.addEventListener('click', function () {
            self.close();
        });

        this.cancelButton.addEventListener('click', function () {
            self.close();
        });

        this.overlay.addEventListener('click', function (event) {
            if (event.target === self.overlay) {
                self.close();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !self.overlay.hidden) {
                self.close();
            }
        });

        this.form.addEventListener('submit', function (event) {
            event.preventDefault();
            self.send();
        });

        this.messageInput.addEventListener('input', function () {
            self.unlockAfterSuccess();
        });

        // Die Seite wurde mit ?tabslFeedback=1 aufgerufen — dann ist der Dialog
        // das Ziel des Aufrufs und geht sofort auf, statt nur den Button zu zeigen.
        if (this.config.autoOpen) {
            this.open();
        }
    };

    FrontendWidget.prototype.buildTrigger = function () {
        var button = createElement(
            'button',
            'tabslfeedback-trigger tabslfeedback-trigger--' + this.config.position
        );
        button.type = 'button';

        var icon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        icon.setAttribute('class', 'tabslfeedback-trigger__icon');
        icon.setAttribute('viewBox', '0 0 16 16');
        icon.setAttribute('aria-hidden', 'true');

        var path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        path.setAttribute(
            'd',
            'M8 1C4.1 1 1 3.6 1 6.8c0 1.8 1 3.4 2.6 4.5L3 15l3.4-1.8c.5.1 1 .2 1.6.2 3.9 0 7-2.6 7-5.8S11.9 1 8 1z'
        );
        icon.appendChild(path);

        button.appendChild(icon);
        button.appendChild(createElement('span', null, this.texts.triggerLabel));

        return button;
    };

    FrontendWidget.prototype.buildDialog = function () {
        var overlay = createElement('div', 'tabslfeedback-overlay');
        overlay.hidden = true;

        this.dialog = createElement('div', 'tabslfeedback-dialog');
        this.dialog.setAttribute('role', 'dialog');
        this.dialog.setAttribute('aria-modal', 'true');
        this.dialog.setAttribute('aria-labelledby', 'tabslfeedback-title');

        var head = createElement('div', 'tabslfeedback-dialog__head');
        var title = createElement('h2', 'tabslfeedback-dialog__title', this.texts.title);
        title.id = 'tabslfeedback-title';

        this.closeButton = createElement('button', 'tabslfeedback-dialog__close', '×');
        this.closeButton.type = 'button';
        this.closeButton.setAttribute('aria-label', this.texts.close);

        head.appendChild(title);
        head.appendChild(this.closeButton);

        this.form = createElement('form');
        this.form.noValidate = true;

        this.messageBox = createElement('p', 'tabslfeedback-message');
        this.messageBox.hidden = true;
        this.messageBox.setAttribute('role', 'status');

        this.form.appendChild(this.messageBox);
        this.form.appendChild(this.buildMessageField());
        this.form.appendChild(this.buildScreenshotField());

        if (this.config.showContactFields) {
            this.form.appendChild(this.buildContactField('name'));
            this.form.appendChild(this.buildContactField('email'));
        }

        if (this.config.turnstileSiteKey) {
            // Bewusst OHNE die Klasse cf-turnstile und ohne data-sitekey: sonst
            // rendert Cloudflare das Widget von sich aus, und die widgetId, die
            // zum Zurücksetzen des Tokens nötig ist, entsteht nie. Das Rendern
            // übernimmt renderTurnstile().
            this.turnstileHolder = createElement('div', 'tabslfeedback-turnstile');
            this.form.appendChild(this.turnstileHolder);
        }

        // Transparenzhinweis direkt vor dem Absenden — nur wenn tatsächlich eine
        // KI-Aufbereitung konfiguriert ist.
        if (this.config.aiNotice) {
            this.form.appendChild(createElement('p', 'tabslfeedback-notice', this.texts.aiNotice));
        }

        this.form.appendChild(this.buildActions());

        this.dialog.appendChild(head);
        this.dialog.appendChild(this.form);
        overlay.appendChild(this.dialog);

        return overlay;
    };

    FrontendWidget.prototype.buildMessageField = function () {
        var field = createElement('div', 'tabslfeedback-field');
        var label = createElement('label', 'tabslfeedback-label', this.texts.messageLabel);

        this.messageInput = createElement('textarea', 'tabslfeedback-textarea');
        this.messageInput.id = 'tabslfeedback-message';
        this.messageInput.name = 'fb_message';
        this.messageInput.maxLength = this.limits.maxMessageLength;
        label.setAttribute('for', this.messageInput.id);

        field.appendChild(label);
        field.appendChild(this.messageInput);

        return field;
    };

    FrontendWidget.prototype.buildScreenshotField = function () {
        var field = createElement('div', 'tabslfeedback-field');

        field.appendChild(createElement('span', 'tabslfeedback-label', this.texts.screenshotsLabel));
        field.appendChild(createElement('span', 'tabslfeedback-hint', this.texts.screenshotsHint));

        this.previewList = createElement('ul', 'tabslfeedback-previews');
        field.appendChild(this.previewList);

        return field;
    };

    FrontendWidget.prototype.buildContactField = function (type) {
        var field = createElement('div', 'tabslfeedback-field');
        var isEmail = type === 'email';
        var label = createElement(
            'label',
            'tabslfeedback-label',
            isEmail ? this.texts.emailLabel : this.texts.nameLabel
        );

        var input = createElement('input', 'tabslfeedback-input');
        input.type = isEmail ? 'email' : 'text';
        input.id = 'tabslfeedback-' + type;
        input.name = isEmail ? 'fb_email' : 'fb_name';
        input.maxLength = this.limits.maxContactLength;
        label.setAttribute('for', input.id);

        if (isEmail) {
            this.emailInput = input;
        } else {
            this.nameInput = input;
        }

        field.appendChild(label);
        field.appendChild(input);

        return field;
    };

    FrontendWidget.prototype.buildActions = function () {
        var actions = createElement('div', 'tabslfeedback-actions');

        this.cancelButton = createElement(
            'button',
            'tabslfeedback-button tabslfeedback-button--secondary',
            this.texts.cancel
        );
        this.cancelButton.type = 'button';

        this.submitButton = createElement(
            'button',
            'tabslfeedback-button tabslfeedback-button--primary',
            this.texts.submit
        );
        this.submitButton.type = 'submit';

        actions.appendChild(this.cancelButton);
        actions.appendChild(this.submitButton);

        return actions;
    };

    FrontendWidget.prototype.open = function () {
        this.overlay.hidden = false;

        // Die Bestätigung des letzten Vorgangs bleibt nach dem Absenden stehen;
        // beim erneuten Öffnen wäre sie irreführend. Mit ihr fällt auch die
        // Sperre des Absenden-Knopfes — für eine neue Meldung.
        this.unlockAfterSuccess();
        this.showMessage('', false);
        this.renderTurnstile();
        this.messageInput.focus();
    };

    FrontendWidget.prototype.close = function () {
        this.overlay.hidden = true;
    };

    /**
     * Rendert das Turnstile-Widget selbst und merkt sich dessen Kennung.
     *
     * Das Cloudflare-Script wird mit `async defer` geladen und teilt sich den
     * Shop womöglich mit tabslTurnstile — beim Öffnen des Dialogs kann es also
     * noch fehlen. Deshalb wird begrenzt erneut versucht, statt das Widget
     * stillschweigend wegzulassen.
     */
    FrontendWidget.prototype.renderTurnstile = function () {
        if (!this.turnstileHolder || this.turnstileWidgetId !== null) {
            return;
        }

        var self = this;

        if (!window.turnstile || typeof window.turnstile.render !== 'function') {
            // Rund zehn Sekunden lang nachfassen, dann aufgeben.
            if (this.turnstileAttempts < 40) {
                this.turnstileAttempts++;
                window.setTimeout(function () {
                    self.renderTurnstile();
                }, 250);
            }

            return;
        }

        try {
            var id = window.turnstile.render(this.turnstileHolder, {
                sitekey: this.config.turnstileSiteKey
            });

            // render() liefert bei Fehlschlag undefined statt zu werfen.
            this.turnstileWidgetId = (id === undefined || id === null) ? null : id;
        } catch (error) {
            this.turnstileWidgetId = null;
        }
    };

    /**
     * Bevorzugt getResponse() mit der eigenen Widget-Kennung; das versteckte
     * Feld dient nur als Rückfall, falls die Methode fehlt.
     */
    FrontendWidget.prototype.readTurnstileToken = function () {
        if (this.turnstileWidgetId !== null
            && window.turnstile
            && typeof window.turnstile.getResponse === 'function'
        ) {
            try {
                return window.turnstile.getResponse(this.turnstileWidgetId) || '';
            } catch (error) {
                // Rückfall unten.
            }
        }

        var field = this.form.querySelector('[name="cf-turnstile-response"]');

        return field ? field.value : '';
    };

    FrontendWidget.prototype.refreshPreviews = function () {
        var self = this;

        renderPreviews(this.previewList, this.store, this.texts, function () {
            self.refreshPreviews();
        });
    };

    FrontendWidget.prototype.showMessage = function (text, success) {
        this.messageBox.textContent = text;
        this.messageBox.className = 'tabslfeedback-message tabslfeedback-message--' + (success ? 'success' : 'error');
        this.messageBox.hidden = !text;
    };

    FrontendWidget.prototype.collectValues = function () {
        return {
            message: this.messageInput.value,
            name: this.nameInput ? this.nameInput.value : '',
            email: this.emailInput ? this.emailInput.value : ''
        };
    };

    FrontendWidget.prototype.send = function () {
        if (this.sending || this.submitted) {
            return;
        }

        var values = this.collectValues();
        var error = validateFields(values, this.limits, this.texts);

        if (error) {
            this.showMessage(error, false);
            return;
        }

        var body = new URLSearchParams();
        body.append('fnc', 'submit');
        body.append('cl', 'tabslfeedback_submit');
        body.append('stoken', this.config.stoken);

        body.append('fb_message', values.message);
        body.append('fb_name', values.name);
        body.append('fb_email', values.email);
        body.append('fb_meta', collectMeta());

        this.store.dataUrls().forEach(function (dataUrl) {
            body.append('fb_images[]', dataUrl);
        });

        var turnstileToken = this.readTurnstileToken();

        if (turnstileToken !== '') {
            body.append('cf-turnstile-response', turnstileToken);
        }

        this.setSending(true);

        var self = this;

        fetch(this.config.url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: body.toString()
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (result) {
                self.setSending(false);

                if (result && result.ok) {
                    self.onSuccess(result.message || self.texts.thanks);
                    return;
                }

                // Der Text bleibt im Feld stehen, damit er nicht neu getippt
                // werden muss (requirements.md B4).
                self.showMessage((result && result.message) || self.texts.errorGeneric, false);
                self.resetTurnstile();
            })
            .catch(function () {
                self.setSending(false);
                self.showMessage(self.texts.errorNetwork, false);
                self.resetTurnstile();
            });
    };

    FrontendWidget.prototype.onSuccess = function (message) {
        this.messageInput.value = '';

        if (this.nameInput) {
            this.nameInput.value = '';
        }

        if (this.emailInput) {
            this.emailInput.value = '';
        }

        this.store.clear();
        this.refreshPreviews();
        this.resetTurnstile();
        this.showMessage(message, true);

        // Gesperrt lassen: die Meldung ist raus, ein weiterer Klick würde nur ein
        // Duplikat erzeugen. Erst eine neue Eingabe gibt den Knopf wieder frei.
        this.submitted = true;
        this.submitButton.disabled = true;
    };

    /**
     * Hebt die Sperre nach einer erfolgreichen Absendung wieder auf, sobald der
     * Melder etwas Neues schreibt oder den Dialog erneut öffnet.
     */
    FrontendWidget.prototype.unlockAfterSuccess = function () {
        if (!this.submitted) {
            return;
        }

        this.submitted = false;
        this.submitButton.disabled = false;
        this.showMessage('', false);
    };

    /**
     * Setzt das Widget über seine Kennung zurück. Ein Token gilt nur einmal —
     * ohne das Zurücksetzen scheiterte ein zweiter Anlauf nach einem Fehler an
     * der Bot-Prüfung, obwohl die Meldung erneut absendbar sein soll (B4).
     */
    FrontendWidget.prototype.resetTurnstile = function () {
        if (this.turnstileWidgetId === null
            || !window.turnstile
            || typeof window.turnstile.reset !== 'function'
        ) {
            return;
        }

        try {
            window.turnstile.reset(this.turnstileWidgetId);
        } catch (error) {
            // Kein verwertbarer Zustand — der nächste Versuch rendert neu.
            this.turnstileWidgetId = null;
        }
    };

    FrontendWidget.prototype.setSending = function (sending) {
        this.sending = sending;
        this.submitButton.disabled = sending;
        this.submitButton.textContent = sending ? this.texts.sending : this.texts.submit;
    };

    /* ------------------------------------------------------------------ *
     * Backend: Paste-Logik am serverseitig gerenderten Formular
     * ------------------------------------------------------------------ */

    function initAdminForm(form) {
        var limits = parseJsonAttribute(form, 'data-limits', {});
        var texts = parseJsonAttribute(form, 'data-texts', {});
        var previewList = form.querySelector('.tabslfeedback-previews');
        var imageContainer = form.querySelector('.tabslfeedback-image-values');
        var messageBox = form.querySelector('.tabslfeedback-message--client');
        var messageInput = form.querySelector('[name="fb_message"]');

        if (!previewList || !imageContainer || !messageInput) {
            return;
        }

        function showError(text) {
            if (!messageBox) {
                return;
            }

            messageBox.textContent = text;
            messageBox.hidden = !text;
        }

        var store = new ImageStore(limits, texts, showError);

        function sync() {
            showError('');
            renderPreviews(previewList, store, texts, sync);

            // Die Bilder reisen als normale POST-Felder mit, nicht als Upload.
            imageContainer.innerHTML = '';

            store.dataUrls().forEach(function (dataUrl) {
                var hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'fb_images[]';
                hidden.value = dataUrl;
                imageContainer.appendChild(hidden);
            });
        }

        bindPaste(form, store, sync);

        var metaField = form.querySelector('[name="fb_meta"]');
        var submitButton = form.querySelector('button[type="submit"]');
        var submitting = false;

        form.addEventListener('submit', function (event) {
            // Das Backend-Formular wird gewöhnlich abgeschickt. Ohne Sperre
            // erzeugt ein Doppelklick zwei Anfragen und damit zwei Tickets.
            if (submitting) {
                event.preventDefault();
                return;
            }

            var nameField = form.querySelector('[name="fb_name"]');
            var emailField = form.querySelector('[name="fb_email"]');

            var error = validateFields(
                {
                    message: messageInput.value,
                    name: nameField ? nameField.value : '',
                    email: emailField ? emailField.value : ''
                },
                limits,
                texts
            );

            if (error) {
                event.preventDefault();
                showError(error);
                return;
            }

            if (metaField) {
                metaField.value = collectMeta();
            }

            submitting = true;

            // Erst nach dem Absenden sperren: ein bereits deaktivierter Knopf
            // würde den Request gar nicht erst auslösen.
            if (submitButton) {
                window.setTimeout(function () {
                    submitButton.disabled = true;

                    if (texts.sending) {
                        submitButton.textContent = texts.sending;
                    }
                }, 0);
            }
        });
    }

    /* ------------------------------------------------------------------ *
     * Einstieg
     * ------------------------------------------------------------------ */

    function init() {
        var adminForm = document.getElementById(ADMIN_FORM_ID);

        if (adminForm) {
            initAdminForm(adminForm);
            return;
        }

        var config = document.getElementById(FRONTEND_CONFIG_ID);

        if (!config) {
            return;
        }

        new FrontendWidget({
            url: config.getAttribute('data-url') || '',
            stoken: config.getAttribute('data-stoken') || '',
            position: config.getAttribute('data-position') || 'bottom-right',
            showContactFields: config.getAttribute('data-contact') === '1',
            autoOpen: config.getAttribute('data-autoopen') === '1',
            aiNotice: config.getAttribute('data-ai-notice') === '1',
            turnstileSiteKey: config.getAttribute('data-turnstile-sitekey') || '',
            limits: parseJsonAttribute(config, 'data-limits', {}),
            texts: parseJsonAttribute(config, 'data-texts', {})
        }).init();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
