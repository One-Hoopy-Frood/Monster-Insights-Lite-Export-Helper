(function () {
    'use strict';

    const settings = window.mihrSettings || {};
    const todayISO = settings.today || new Date().toISOString().slice(0, 10);
    const strings = settings.strings || {};
    const futureDateMessage = strings.futureDateMessage || 'Data is only available up to today.';
    const todayReference = new Date(todayISO + 'T00:00:00');
    const monitoredSelectors = [
        'input[type="date"][name*="end" i]',
        'input[type="text"][name*="end" i]',
        'input[aria-label*="end" i]',
        'input[placeholder*="end" i]',
        'input[data-testid*="end" i]',
        '.monsterinsights-date-range input'
    ];
    const processedInputs = new WeakSet();
    const ERROR_SNIPPET = 'Please select a valid date range';
    let lastAlertAt = 0;

    function init() {
        if (!document.body) {
            window.addEventListener('DOMContentLoaded', init, { once: true });
            return;
        }

        attachInputs(document);
        observeDom();
        scanForError(document.body);
    }

    function attachInputs(root) {
        getCandidateInputs(root).forEach(wireInput);
    }

    function getCandidateInputs(root) {
        if (!root || typeof root.querySelectorAll !== 'function') {
            return [];
        }

        const found = new Set();
        monitoredSelectors.forEach((selector) => {
            root.querySelectorAll(selector).forEach((node) => {
                if (isInput(node)) {
                    found.add(node);
                }
            });
        });

        return Array.from(found);
    }

    function isInput(node) {
        return Boolean(node && node.tagName && node.tagName.toLowerCase() === 'input');
    }

    function wireInput(input) {
        if (processedInputs.has(input)) {
            return;
        }
        processedInputs.add(input);
        input.addEventListener('change', onDateFieldChange);
        input.addEventListener('blur', onDateFieldChange);
    }

    function onDateFieldChange(event) {
        const input = event.target;
        if (!input) {
            return;
        }

        if (clampInputValue(input)) {
            showFutureDateAlert();
        }
    }

    function clampInputValue(input, force = false) {
        const parsed = parseDate(input.value);
        if (!force) {
            if (!parsed) {
                return false;
            }

            if (parsed <= todayReference) {
                return false;
            }
        }

        const newValue = formatDateForInput(input);
        if (!force && input.value === newValue) {
            return false;
        }

        input.value = newValue;
        triggerSyntheticEvents(input);
        return true;
    }

    function parseDate(value) {
        if (!value) {
            return null;
        }

        const trimmed = String(value).trim();
        if (!trimmed) {
            return null;
        }

        if (/^\d{4}-\d{2}-\d{2}$/.test(trimmed)) {
            return new Date(trimmed + 'T00:00:00');
        }

        const timestamp = Date.parse(trimmed);
        if (Number.isNaN(timestamp)) {
            return null;
        }

        return new Date(timestamp);
    }

    function formatDateForInput(input) {
        if (input && input.type === 'date') {
            return todayISO;
        }

        return todayReference.toLocaleDateString(undefined, {
            year: 'numeric',
            month: 'short',
            day: '2-digit'
        });
    }

    function triggerSyntheticEvents(input) {
        ['input', 'change'].forEach((eventName) => {
            const event = new Event(eventName, { bubbles: true });
            input.dispatchEvent(event);
        });
    }

    function showFutureDateAlert() {
        const now = Date.now();
        if (now - lastAlertAt < 1200) {
            return;
        }

        lastAlertAt = now;
        if (typeof window.alert === 'function') {
            window.alert(futureDateMessage);
        }
    }

    function observeDom() {
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (node.nodeType === Node.ELEMENT_NODE) {
                        attachInputs(node);
                        scanForError(node);
                    } else if (node.nodeType === Node.TEXT_NODE) {
                        inspectTextNode(node);
                    }
                });

                if (mutation.type === 'characterData' && mutation.target) {
                    inspectTextNode(mutation.target);
                }
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true,
            characterData: true
        });
    }

    function inspectTextNode(node) {
        if (!node || !node.textContent) {
            return;
        }

        if (node.textContent.indexOf(ERROR_SNIPPET) !== -1) {
            handleInvalidDateRange();
        }
    }

    function scanForError(root) {
        if (!root) {
            return;
        }

        if (root.nodeType === Node.TEXT_NODE) {
            inspectTextNode(root);
            return;
        }

        if (root.textContent && root.textContent.indexOf(ERROR_SNIPPET) !== -1) {
            handleInvalidDateRange();
            return;
        }

        if (typeof root.querySelectorAll === 'function') {
            root.querySelectorAll('*').forEach((node) => {
                if (node.textContent && node.textContent.indexOf(ERROR_SNIPPET) !== -1) {
                    handleInvalidDateRange();
                }
            });
        }
    }

    function handleInvalidDateRange() {
        showFutureDateAlert();
        forceEndDateToToday();
    }

    function forceEndDateToToday() {
        const inputs = getCandidateInputs(document);
        for (let i = 0; i < inputs.length; i += 1) {
            if (clampInputValue(inputs[i], true)) {
                return true;
            }
        }
        return false;
    }

    init();
})();
