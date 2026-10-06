(() => {
    const appBase = (window.APP_BASE || document.documentElement.dataset.appBase || '').replace(/\/$/, '');
    const appUrl = (path) => `${appBase}/${String(path).replace(/^\//, '')}`;

    const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
    if (sidebarToggle) {
        const sidebarScrim = document.querySelector('[data-sidebar-close]');
        const closeAdminNavigation = () => {
            document.body.classList.remove('admin-nav-open');
            sidebarToggle.setAttribute('aria-expanded', 'false');
            if (sidebarScrim) sidebarScrim.hidden = true;
        };
        const collapsed = localStorage.getItem('sidebarCollapsed') === '1';
        document.body.classList.toggle('sidebar-collapsed', collapsed);
        sidebarToggle.addEventListener('click', () => {
            if (document.body.classList.contains('admin-mode') && window.matchMedia('(max-width: 820px)').matches) {
                document.body.classList.toggle('admin-nav-open');
                sidebarToggle.setAttribute('aria-expanded', document.body.classList.contains('admin-nav-open') ? 'true' : 'false');
                if (sidebarScrim) sidebarScrim.hidden = !document.body.classList.contains('admin-nav-open');
                return;
            }
            const next = !document.body.classList.contains('sidebar-collapsed');
            document.body.classList.toggle('sidebar-collapsed', next);
            localStorage.setItem('sidebarCollapsed', next ? '1' : '0');
        });
        document.querySelectorAll('.admin-navigation a, .admin-sidebar-foot a').forEach((link) => {
            link.addEventListener('click', closeAdminNavigation);
        });
        sidebarScrim?.addEventListener('click', closeAdminNavigation);
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') closeAdminNavigation();
        });
    }

    const currency = new Intl.NumberFormat('en-NG', {
        style: 'currency',
        currency: 'NGN',
        maximumFractionDigits: 2
    });

    const ensureFeedbackModal = () => {
        let backdrop = document.querySelector('[data-feedback-backdrop]');
        if (backdrop) return backdrop;

        backdrop = document.createElement('div');
        backdrop.className = 'feedback-backdrop';
        backdrop.hidden = true;
        backdrop.dataset.feedbackBackdrop = '1';
        backdrop.innerHTML = `
            <section class="feedback-modal" role="dialog" aria-modal="true" aria-live="polite">
                <div class="feedback-icon" data-feedback-icon></div>
                <h2 data-feedback-title></h2>
                <p data-feedback-message></p>
                <div class="feedback-actions">
                    <button type="button" data-feedback-cancel hidden>Cancel</button>
                    <button type="button" data-feedback-ok>OK</button>
                </div>
            </section>
        `;
        document.body.appendChild(backdrop);
        return backdrop;
    };

    const showFeedback = ({ type = 'success', title = 'Success', message = '', okText = 'OK', cancelText = '', persistent = false } = {}) => new Promise((resolve) => {
        const backdrop = ensureFeedbackModal();
        const icon = backdrop.querySelector('[data-feedback-icon]');
        const titleEl = backdrop.querySelector('[data-feedback-title]');
        const messageEl = backdrop.querySelector('[data-feedback-message]');
        const ok = backdrop.querySelector('[data-feedback-ok]');
        const cancel = backdrop.querySelector('[data-feedback-cancel]');
        const close = (value) => {
            if (!persistent) {
                backdrop.hidden = true;
                document.body.classList.remove('modal-open');
            }
            ok.onclick = null;
            cancel.onclick = null;
            resolve(value);
        };

        backdrop.className = `feedback-backdrop ${type}`;
        icon.textContent = type === 'processing' ? '' : (type === 'error' ? '×' : type === 'confirm' ? '!' : '✓');
        icon.className = `feedback-icon ${type}`;
        titleEl.textContent = title;
        messageEl.textContent = message;
        ok.textContent = okText;
        cancel.textContent = cancelText || 'Cancel';
        cancel.hidden = !cancelText;
        backdrop.hidden = false;
        document.body.classList.add('modal-open');
        ok.onclick = () => close(true);
        cancel.onclick = () => close(false);
        if (!persistent) {
            backdrop.onclick = (event) => {
                if (event.target === backdrop && !cancelText) close(true);
            };
        }
    });

    const showProcessing = (message = 'Please wait while we process your request...') => {
        showFeedback({
            type: 'processing',
            title: 'Processing',
            message,
            okText: 'Please wait',
            persistent: true
        });
        const backdrop = ensureFeedbackModal();
        const ok = backdrop.querySelector('[data-feedback-ok]');
        if (ok) ok.disabled = true;
    };

    document.querySelectorAll('.alert').forEach((alert) => {
        const message = alert.textContent.trim();
        if (!message) return;
        const isError = /\b(error|failed|invalid|cannot|disabled|rejected|denied|not found|insufficient|wrong)\b/i.test(message);
        showFeedback({
            type: isError ? 'error' : 'success',
            title: isError ? 'Failed' : 'Success',
            message
        });
    });

    document.querySelectorAll('.js-service-form').forEach((form) => {
        const service = form.dataset.service;
        const planSelect = form.querySelector('[data-plan-select]');
        const amountInput = form.querySelector('[data-amount-input]');
        const planHelp = form.querySelector('[data-plan-help]');
        setupCustomerVerification(form, service);
        const ux = {
            data: {
                recipient: 'Data Phone Number',
                amount: 'Amount',
                recipientPlaceholder: '08031234567',
                amountPlaceholder: 'Auto-filled after selecting a data plan'
            },
            airtime: {
                recipient: 'Airtime Phone Number',
                amount: 'Airtime Amount',
                recipientPlaceholder: '08031234567',
                amountPlaceholder: 'Minimum ₦50'
            },
            electricity: {
                recipient: 'Prepaid Meter Number',
                amount: 'Electricity Amount',
                recipientPlaceholder: 'Enter prepaid meter number',
                amountPlaceholder: 'Enter amount to pay'
            },
            cable: {
                recipient: 'Smartcard / IUC Number',
                amount: 'Subscription Amount',
                recipientPlaceholder: 'Enter smartcard or IUC number',
                amountPlaceholder: 'Auto-filled after selecting a package'
            },
            exam_epin: {
                recipient: 'Customer Phone Number',
                amount: 'ePIN Amount',
                recipientPlaceholder: '08031234567',
                amountPlaceholder: 'Auto-filled after selecting an exam plan'
            },
            data_pin: {
                recipient: 'Receiver Phone Number',
                amount: 'Data PIN Amount',
                recipientPlaceholder: '08031234567',
                amountPlaceholder: 'Auto-filled after selecting a PIN plan'
            },
            airtime_pin: {
                recipient: 'Receiver Phone Number',
                amount: 'Airtime PIN Amount',
                recipientPlaceholder: '08031234567',
                amountPlaceholder: 'Enter denomination, e.g. 100'
            }
        }[service] || {};

        Object.entries(ux).forEach(([key, value]) => {
            if (!value) return;
            if (key === 'recipient') {
                const label = form.querySelector('[data-dynamic-label="recipient"]');
                if (label) label.textContent = value;
            }
            if (key === 'amount') {
                const label = form.querySelector('[data-dynamic-label="amount"]');
                if (label) label.textContent = value;
            }
            if (key === 'recipientPlaceholder') {
                const field = form.querySelector('[name="recipient"]');
                if (field) field.placeholder = value;
            }
            if (key === 'amountPlaceholder') {
                const field = form.querySelector('[name="amount"]');
                if (field) field.placeholder = value;
            }
        });

        if (!service || !planSelect || !amountInput) {
            const bulkToggle = form.querySelector('[data-bulk-toggle]');
            if (bulkToggle) {
                setupBulkToggle(form, bulkToggle);
            }
            return;
        }

        const bulkToggle = form.querySelector('[data-bulk-toggle]');
        if (bulkToggle) {
            setupBulkToggle(form, bulkToggle);
        }

        const selectedFilters = () => {
            const params = new URLSearchParams();
            const provider = form.querySelector('[name="provider"]')?.value || '';
            const serviceType = form.querySelector('[name="service_type"]')?.value || '';
            if (provider) params.set('provider', provider);
            if (serviceType) params.set('service_type', serviceType);
            return params.toString();
        };

        const loadPlans = () => {
            const originalFirst = planSelect.querySelector('option[value=""]')?.textContent || 'Select plan';
            planSelect.innerHTML = '';
            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = 'Loading plans...';
            planSelect.appendChild(placeholder);
            planSelect.disabled = true;
            if (planHelp) planHelp.textContent = 'Checking available plans...';

            const query = selectedFilters();
            fetch(appUrl(`/api/plans/${encodeURIComponent(service)}${query ? `?${query}` : ''}`), {
                headers: { 'Accept': 'application/json' }
            })
            .then((response) => response.ok ? response.json() : { plans: [] })
            .then((data) => {
                const plans = Array.isArray(data.plans) ? data.plans : [];
                planSelect.innerHTML = '';
                const first = document.createElement('option');
                first.value = '';
                first.textContent = plans.length ? originalFirst : 'No active plan available';
                planSelect.appendChild(first);
                planSelect.disabled = plans.length === 0;
                if (planHelp) {
                    planHelp.textContent = plans.length ? `${plans.length} active plan(s) available.` : 'No active plan is available for this network/service type. Please choose another option.';
                }

                plans.forEach((plan) => {
                    const option = document.createElement('option');
                    option.value = plan.plan_id;
                    option.dataset.amount = plan.selling_price;
                    option.dataset.provider = plan.provider;
                    option.dataset.serviceType = plan.service_type || '';
                    const planType = plan.service_type ? ` · ${plan.service_type}` : '';
                    const duration = plan.duration ? ` · ${plan.duration}` : '';
                    const quantity = Number(plan.quantity || 0) > 1 ? ` · Qty ${plan.quantity}` : '';
                    option.textContent = service === 'cable'
                        ? `${plan.name}${duration} · ${currency.format(Number(plan.selling_price || 0))}`
                        : `${plan.provider.toUpperCase()}${planType} · ${plan.name}${duration}${quantity} · ${currency.format(Number(plan.selling_price || 0))}`;
                    planSelect.appendChild(option);
                });
            });
        };

        loadPlans();
        form.querySelectorAll('[data-plan-filter]').forEach((field) => {
            field.addEventListener('change', loadPlans);
        });

        planSelect.addEventListener('change', () => {
            const selected = planSelect.selectedOptions[0];
            if (!selected) {
                return;
            }

            if (selected.dataset.amount) {
                amountInput.value = selected.dataset.amount;
            }

            const provider = form.querySelector('[name="provider"]');
            if (provider && selected.dataset.provider) {
                provider.value = selected.dataset.provider;
            }
            const serviceType = form.querySelector('[name="service_type"]');
            if (serviceType && selected.dataset.serviceType) {
                serviceType.value = selected.dataset.serviceType;
            }
        });
    });

    function setupBulkToggle(form, toggle) {
        const box = form.querySelector('.bulk-recipient-box');
        const bulkRecipients = form.querySelector('[name="bulk_recipients"]');
        const singleRecipient = form.querySelector('[data-single-recipient], [name="recipient"]');
        const sync = () => {
            const enabled = toggle.checked;
            if (box) box.hidden = !enabled;
            if (bulkRecipients) {
                bulkRecipients.required = enabled;
                if (!enabled) bulkRecipients.value = '';
            }
            if (singleRecipient) {
                singleRecipient.required = !enabled;
                singleRecipient.closest('label')?.classList.toggle('is-soft-disabled', enabled);
            }
        };
        toggle.addEventListener('change', sync);
        sync();
    }

    function setupCustomerVerification(form, service) {
        const button = form.querySelector('[data-verify-customer]');
        const result = form.querySelector('[data-verify-result]');
        const verifiedName = form.querySelector('[data-verified-name]');
        const recipient = form.querySelector('[data-verify-recipient], [name="recipient"]');
        if (!button || !result || !recipient || !service) {
            return;
        }

        const verify = () => {
            const provider = form.querySelector('[name="provider"]')?.value || '';
            const planId = form.querySelector('[name="plan_id"]')?.value || '';
            const value = recipient.value.trim();
            if (!provider || !value) {
                result.textContent = 'Enter provider and customer number first.';
                result.className = 'verification-result bad';
                return;
            }
            button.disabled = true;
            result.textContent = 'Verifying...';
            result.className = 'verification-result';
            const params = new URLSearchParams({ provider, recipient: value });
            if (planId) params.set('plan_id', planId);
            fetch(appUrl(`/api/verify/${encodeURIComponent(service)}?${params.toString()}`), {
                headers: { 'Accept': 'application/json' }
            })
            .then((response) => response.json().then((body) => ({ ok: response.ok, body })))
            .then(({ ok, body }) => {
                if (!ok || !body.ok) {
                    throw new Error(body.message || 'Verification failed.');
                }
                const name = body.name || 'Verified customer';
                result.textContent = `${name}${body.address ? ` · ${body.address}` : ''}`;
                result.className = 'verification-result ok';
                if (verifiedName) verifiedName.value = name;
            })
            .catch((error) => {
                result.textContent = error.message || 'Verification failed.';
                result.className = 'verification-result bad';
                if (verifiedName) verifiedName.value = '';
            })
            .finally(() => {
                button.disabled = false;
            });
        };

        button.addEventListener('click', verify);
        recipient.addEventListener('blur', () => {
            if (recipient.value.trim().length >= 5) {
                verify();
            }
        });
    }

    document.querySelectorAll('[data-confirm]').forEach((button) => {
        button.addEventListener('click', async (event) => {
            const form = button.closest('form');
            if (form?.dataset.confirmed === '1') {
                delete form.dataset.confirmed;
                return;
            }
            event.preventDefault();
            const confirmed = await showFeedback({
                type: 'confirm',
                title: 'Please confirm',
                message: button.dataset.confirm || 'Continue with this action?',
                okText: 'Yes, continue',
                cancelText: 'Cancel'
            });
            if (!confirmed) return;
            if (form) {
                form.dataset.confirmed = '1';
                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit(button);
                } else {
                    form.submit();
                }
            }
        });
    });

    const setupProviderPreset = (preset, notesSelector) => {
        const form = preset.closest('form');
        const notes = document.querySelector(notesSelector);
        const setValue = (name, value) => {
            const field = form.querySelector(`[name="${name}"]`);
            if (field) field.value = value || '';
        };

        preset.addEventListener('change', () => {
            const option = preset.selectedOptions[0];
            if (!option || !option.value) {
                if (notes) notes.textContent = '';
                return;
            }

            setValue('service', option.dataset.service);
            setValue('provider_key', option.value);
            setValue('name', option.dataset.name);
            setValue('base_url', option.dataset.baseUrl);
            setValue('auth_type', option.dataset.authType || 'none');
            setValue('mappings', option.dataset.mappings || '{}');
            if (notes) {
                notes.textContent = `Required: ${option.dataset.required || 'none'} · ${option.dataset.notes || ''}`;
            }
        });
    };

    const preset = document.querySelector('#provider-preset');
    if (preset) {
        setupProviderPreset(preset, '#provider-preset-notes');
    }

    const servicePreset = document.querySelector('#service-provider-preset');
    if (servicePreset) {
        setupProviderPreset(servicePreset, '#service-provider-preset-notes');
    }

    const settingsTabs = document.querySelectorAll('[data-settings-tab]');
    if (settingsTabs.length) {
        const panels = document.querySelectorAll('[data-settings-panel]');
        const activateSettingsTab = (name) => {
            settingsTabs.forEach((button) => {
                const active = button.dataset.settingsTab === name;
                button.classList.toggle('active', active);
                button.setAttribute('aria-selected', active ? 'true' : 'false');
                button.tabIndex = active ? 0 : -1;
            });
            panels.forEach((panel) => {
                const active = panel.dataset.settingsPanel === name;
                panel.hidden = !active;
                panel.classList.toggle('active', active);
            });
            try {
                sessionStorage.setItem('adminSettingsTab', name);
            } catch (_) {
                // Tabs remain functional when browser storage is unavailable.
            }
        };
        settingsTabs.forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                activateSettingsTab(button.dataset.settingsTab || 'general');
            });
        });
        let savedTab = 'general';
        try {
            savedTab = sessionStorage.getItem('adminSettingsTab') || 'general';
        } catch (_) {
            savedTab = 'general';
        }
        activateSettingsTab(document.querySelector(`[data-settings-panel="${savedTab}"]`) ? savedTab : 'general');
    }

    const endpointPanel = document.querySelector('[data-gateway-endpoints]');
    const gatewaySelect = document.querySelector('[name="settings[funding_gateway_provider]"]');
    if (endpointPanel && gatewaySelect) {
        const updateGatewayEndpoints = () => {
            const provider = encodeURIComponent(gatewaySelect.value || 'gateway');
            const callback = endpointPanel.querySelector('[data-callback-url]');
            const webhook = endpointPanel.querySelector('[data-webhook-url]');
            if (callback) callback.value = `${endpointPanel.dataset.callbackBase}?provider=${provider}`;
            if (webhook) webhook.value = `${endpointPanel.dataset.webhookBase}/${provider}`;
        };
        gatewaySelect.addEventListener('change', updateGatewayEndpoints);
        updateGatewayEndpoints();
    }

    const copyInputValue = async (input, button) => {
        if (!input) return;
        try {
            await navigator.clipboard.writeText(input.value);
        } catch (_) {
            input.focus();
            input.select();
            document.execCommand('copy');
        }
        const original = button.textContent;
        button.textContent = 'Copied';
        setTimeout(() => { button.textContent = original; }, 1200);
    };
    document.querySelectorAll('[data-copy-target]').forEach((button) => {
        button.addEventListener('click', () => {
            const selector = button.dataset.copyTarget === 'webhook' ? '[data-webhook-url]' : '[data-callback-url]';
            copyInputValue(document.querySelector(selector), button);
        });
    });
    document.querySelectorAll('[data-copy-adjacent]').forEach((button) => {
        button.addEventListener('click', () => copyInputValue(button.parentElement?.querySelector('input'), button));
    });

    const logoInput = document.querySelector('[data-logo-input]');
    if (logoInput) {
        logoInput.addEventListener('change', () => {
            const file = logoInput.files?.[0];
            const preview = document.querySelector('[data-logo-preview]');
            if (!file || !preview || !file.type.startsWith('image/')) return;
            const image = document.createElement('img');
            image.alt = 'Selected logo preview';
            image.src = URL.createObjectURL(file);
            image.onload = () => URL.revokeObjectURL(image.src);
            preview.replaceChildren(image);
        });
    }

    const builder = document.querySelector('[data-homepage-builder]');
    if (builder) {
        let dragged = null;
        const orderInput = document.querySelector('#section-order');
        const updateOrder = () => {
            if (orderInput) {
                orderInput.value = Array.from(builder.querySelectorAll('[data-id]')).map((item) => item.dataset.id).join(',');
            }
        };

        builder.querySelectorAll('.builder-section').forEach((section) => {
            section.addEventListener('dragstart', () => {
                dragged = section;
                section.classList.add('dragging');
            });
            section.addEventListener('dragend', () => {
                section.classList.remove('dragging');
                dragged = null;
                updateOrder();
            });
            section.addEventListener('dragover', (event) => {
                event.preventDefault();
                if (!dragged || dragged === section) {
                    return;
                }
                const rect = section.getBoundingClientRect();
                const before = event.clientY < rect.top + rect.height / 2;
                builder.insertBefore(dragged, before ? section : section.nextSibling);
            });
        });
        updateOrder();
    }

    document.querySelectorAll('[data-edit-section]').forEach((button) => {
        button.addEventListener('click', () => {
            const set = (id, value) => {
                const field = document.querySelector(id);
                if (field) field.value = value || '';
            };
            set('#builder-id', button.dataset.id);
            set('#builder-type', button.dataset.type);
            set('#builder-title', button.dataset.title);
            set('#builder-subtitle', button.dataset.subtitle);
            set('#builder-content-text', button.dataset.contentText);
            set('#builder-button-label', button.dataset.buttonLabel);
            set('#builder-button-url', button.dataset.buttonUrl);
            set('#builder-sort-order', button.dataset.sortOrder);
            set('#builder-status', button.dataset.status);
            set('#builder-items', button.dataset.items);
            document.querySelector('.builder-form')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

    document.querySelectorAll('[data-package-price-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const target = document.getElementById(button.dataset.packagePriceToggle || '');
            if (target) {
                target.hidden = !target.hidden;
            }
        });
    });

    const showFieldError = (field, message) => {
        let error = field.parentElement?.querySelector('.field-error');
        if (!error) {
            error = document.createElement('small');
            error.className = 'field-error';
            field.parentElement?.appendChild(error);
        }
        error.textContent = message;
        field.classList.toggle('is-invalid', Boolean(message));
    };

    const validators = {
        phone(field) {
            if (!field.required && field.value.trim() === '') {
                return '';
            }
            const digits = field.value.replace(/\D/g, '');
            if (field.value && field.value !== digits) {
                field.value = digits;
            }
            return digits.length === 0 || digits.length === 11 ? '' : 'Phone number must be exactly 11 digits.';
        },
        bulkPhones(field) {
            if (!field.required && field.value.trim() === '') {
                return '';
            }
            const values = field.value.split(',').map((value) => value.replace(/\D/g, '').trim()).filter(Boolean);
            if (values.length === 0) {
                return 'Enter at least one phone number.';
            }
            return values.every((value) => value.length === 11) ? '' : 'Every bulk phone number must be 11 digits and separated by commas.';
        },
        amount(field) {
            const min = Number(field.dataset.minAmount || field.min || 0);
            const value = Number(field.value || 0);
            return min > 0 && field.value !== '' && value < min ? `Minimum amount is ${currency.format(min)}.` : '';
        },
        passwordMatch(form) {
            const password = form.querySelector('[name="password"]');
            const confirm = form.querySelector('[name="password_confirmation"]');
            if (!password || !confirm || confirm.value === '') {
                if (confirm) showFieldError(confirm, '');
                return '';
            }
            const message = password.value === confirm.value ? '' : 'Password and confirmation must match.';
            showFieldError(confirm, message);
            return message;
        },
        passwordLength(field) {
            const min = Number(field.getAttribute('minlength') || 0);
            return min > 0 && field.value !== '' && field.value.length < min ? `Password must be at least ${min} characters.` : '';
        },
        strongPassword(field) {
            if (field.value === '') {
                return '';
            }
            const hasLetter = /[A-Za-z]/.test(field.value);
            const hasNumber = /\d/.test(field.value);
            const hasSymbol = /[^A-Za-z0-9]/.test(field.value);
            return hasLetter && hasNumber && hasSymbol ? '' : 'Use letters, numbers, and symbols.';
        }
    };

    const validateField = (field) => {
        let message = '';
        if (field.matches('[data-phone-field], [name="phone"], [name="recipient"][inputmode="tel"]')) {
            message = validators.phone(field);
        }
        if (!message && field.matches('[name="bulk_recipients"]')) {
            message = validators.bulkPhones(field);
        }
        if (!message && field.matches('[data-amount-input], [name="amount"]')) {
            message = validators.amount(field);
        }
        if (!message && field.matches('[type="password"][minlength]')) {
            message = validators.passwordLength(field);
        }
        if (!message && field.matches('[data-strong-password]')) {
            message = validators.strongPassword(field);
        }
        showFieldError(field, message);
        return message === '';
    };

    document.querySelectorAll('.js-validate-form, .js-service-form').forEach((form) => {
        form.querySelectorAll('input, select, textarea').forEach((field) => {
            field.addEventListener('input', () => {
                validateField(field);
                validators.passwordMatch(form);
            });
            field.addEventListener('blur', () => {
                validateField(field);
                validators.passwordMatch(form);
            });
        });

        form.addEventListener('submit', (event) => {
            const fieldsOk = Array.from(form.querySelectorAll('input, select, textarea')).every(validateField);
            const passwordsOk = validators.passwordMatch(form) === '';
            if (!fieldsOk || !passwordsOk) {
                event.preventDefault();
            }
        });
    });

    document.addEventListener('submit', (event) => {
        if (event.defaultPrevented) {
            return;
        }
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || form.dataset.noProcessing === '1') {
            return;
        }
        const method = (form.getAttribute('method') || 'get').toLowerCase();
        if (method !== 'post') {
            return;
        }
        const submitterText = event.submitter?.textContent?.trim();
        const message = submitterText ? `${submitterText} is being processed...` : 'Your request is being processed...';
        showProcessing(message);
    });

    const electricityToken = document.querySelector('[data-electricity-token]');
    if (electricityToken && electricityToken.dataset.electricityToken) {
        showFeedback({
            type: 'success',
            title: 'Electricity purchase successful',
            message: `Token: ${electricityToken.dataset.electricityToken}`,
            okText: 'Print receipt',
            cancelText: 'Close'
        }).then((print) => {
            if (print) setTimeout(() => window.print(), 150);
        });
    }

    document.querySelectorAll('[data-print-receipt]').forEach((button) => {
        button.addEventListener('click', () => {
            setTimeout(() => window.print(), 50);
        });
    });

    const payloadBackdrop = document.querySelector('[data-payload-backdrop]');
    const payloadContent = document.querySelector('[data-payload-content]');
    const payloadReference = document.querySelector('[data-payload-reference]');
    const closePayloadModal = () => {
        if (payloadBackdrop) {
            payloadBackdrop.hidden = true;
            document.body.classList.remove('modal-open');
        }
    };

    document.querySelectorAll('[data-payload-modal]').forEach((button) => {
        button.addEventListener('click', () => {
            if (!payloadBackdrop || !payloadContent) {
                return;
            }
            const source = document.getElementById(button.dataset.payloadTarget || '');
            const payloadText = source?.tagName === 'TEMPLATE'
                ? source.content.textContent
                : source?.textContent;
            payloadContent.textContent = payloadText?.trim() || '{}';
            if (payloadReference) {
                payloadReference.textContent = button.dataset.reference ? `Reference: ${button.dataset.reference}` : '';
            }
            payloadBackdrop.hidden = false;
            document.body.classList.add('modal-open');
        });
    });

    document.querySelectorAll('[data-payload-close]').forEach((button) => {
        button.addEventListener('click', closePayloadModal);
    });

    payloadBackdrop?.addEventListener('click', (event) => {
        if (event.target === payloadBackdrop) {
            closePayloadModal();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closePayloadModal();
        }
    });
})();
