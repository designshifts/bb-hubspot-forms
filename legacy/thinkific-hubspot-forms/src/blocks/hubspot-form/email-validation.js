/**
 * Email Input Validation & Correction
 *
 * This script adds intelligent validation to all input fields with the attribute: data-email-validation="true"
 * It detects and suggests corrections for common typos in email addresses, including:
 *   - Mistyped free email domains (e.g., "gamil.com" → "gmail.com")
 *   - Invalid or mistyped top-level domains (TLDs) (e.g., ".con" → ".com")
 *   - Missing "@" symbol
 *
 * On blur, if an issue is found:
 *   - A "Did you mean ..." suggestion appears directly below the input
 *   - Clicking the suggestion auto-corrects the email and shows an inline "Undo" link
 *   - Undo restores the original mistyped value
 *
 * All messaging is rendered inline — no external container like #emailWarning is required.
 * Works with multiple inputs on the same page.
 */

import { pushFormInteraction } from "./helper";


const knownSLDs = [
    'gmail', 'outlook', 'hotmail', 'live', 'yahoo',
    'icloud', 'me', 'mac', 'protonmail', 'zoho', 'gmx', 'yandex', 'aol', 'proton'
];

const knownTLDs = ['com', 'ca', 'co', 'uk', 'org', 'br', 'au', 'net', 'gov', 'edu', 'info'];
const errorRed = '#FF6A60'

const levenshtein = (a, b) => {
    const matrix = Array.from({ length: a.length + 1 }, (_, i) =>
        Array.from({ length: b.length + 1 }, (_, j) => (i === 0 ? j : j === 0 ? i : 0))
    );

    for (let i = 1; i <= a.length; i++) {
        for (let j = 1; j <= b.length; j++) {
            matrix[i][j] = a[i - 1] === b[j - 1]
                ? matrix[i - 1][j - 1]
                : Math.min(
                    matrix[i - 1][j] + 1,
                    matrix[i][j - 1] + 1,
                    matrix[i - 1][j - 1] + 1
                );
        }
    }

    return matrix[a.length][b.length];
};

const splitDomain = (domain) => {
    const parts = domain.toLowerCase().split('.');
    const sld = parts[0];
    const tlds = parts.slice(1);
    return { sld, tlds };
};

const getClosestMatch = (input, list, maxDistance = 2) => {
    let closest = null;
    let minDistance = Infinity;

    for (const item of list) {
        const dist = levenshtein(input, item);
        if (dist <= maxDistance && dist < minDistance) {
            closest = item;
            minDistance = dist;
        }
    }

    return closest;
};

const suggestEmailCorrection = (email) => {
    if (!email.includes('@')) {
        pushFormInteraction({
            event_type: "form_error",
            message: "missing @ symbol",
            field_name: "form_interaction"
        });
        return { error: 'Your email doesn’t contain an “@” symbol. Please give it a second look.' };
    }

    const [name, domain] = email.split('@');
    const { sld, tlds } = splitDomain(domain);
    const correctedSLD = getClosestMatch(sld, knownSLDs);
    const sldChanged = !!correctedSLD && correctedSLD !== sld;

    if (sldChanged) {
        pushFormInteraction({
            event_type: "form_error",
            message: "invalid free domain",
            field_name: correctedSLD
        });
    }
    const correctedTLDParts = tlds.map((tld) => {
        const match = getClosestMatch(tld, knownTLDs);
        return { original: tld, corrected: match || tld, changed: !!match && match !== tld };
    });
    const correctedTLDs = correctedTLDParts.map(p => p.corrected);

    correctedTLDParts.filter(p => p.changed).forEach(p => {
        pushFormInteraction({
            event_type: "form_error",
            message: "invalid tld",
            field_name: `.${p.corrected}`
        });
    });
    const tldChanged = correctedTLDs.join('.') !== tlds.join('.');

    if (!sldChanged && !tldChanged) return null;

    return {
        suggestion: `${name}@${correctedSLD || sld}.${correctedTLDs.join('.')}`,
        original: email
    };
};

document.addEventListener('DOMContentLoaded', () => {
    const inputs = document.querySelectorAll('[data-email-validation="true"]');

    if (inputs.length > 0) {
        inputs.forEach(input => {
            let lastOriginalEmail = null;

            const inlineMessage = document.createElement('div');
            inlineMessage.className = 'email-fixit-message';

            inlineMessage.style.fontSize = '.8em';
            inlineMessage.style.marginTop = '4px';
            inlineMessage.style.fontFamily = 'Instrument Sans';
            input.insertAdjacentElement('afterend', inlineMessage);

            input.addEventListener('blur', () => {
                const email = input.value.trim().toLowerCase();
                const result = suggestEmailCorrection(email);

                if (email === '') {
                    inlineMessage.innerHTML = '';
                    return;
                }

                if (result?.error) {
                    inlineMessage.innerHTML = result.error;
                    inlineMessage.style.color = errorRed;
                    return;
                }

                if (result) {
                    lastOriginalEmail = email;

                    const suggestionText = document.createElement('span');
                    suggestionText.textContent = result.suggestion;
                    suggestionText.style.textDecoration = 'underline';
                    suggestionText.style.cursor = 'pointer';
                    suggestionText.style.display = 'inline';
                    suggestionText.className = 'suggestion-email';

                    inlineMessage.innerHTML = 'Did you mean ';
                    inlineMessage.style.color = errorRed;
                    inlineMessage.appendChild(suggestionText);
                    inlineMessage.appendChild(document.createTextNode('?'));


                    suggestionText.addEventListener('click', () => {
                        input.value = result.suggestion;
                        pushFormInteraction({ event_type: "form_error", message: "fix it used" });
                        const undoSpan = document.createElement('span');
                        undoSpan.textContent = 'Undo';
                        undoSpan.style.textDecoration = 'underline';
                        undoSpan.style.cursor = 'pointer';
                        undoSpan.style.marginLeft = '8px';
                        undoSpan.className = 'undo-fix';
                        undoSpan.style.display = 'inline';
                        inlineMessage.style.color = '#B7B1A6';
                        inlineMessage.innerHTML = `Corrected to <strong>${result.suggestion}</strong>. `;
                        inlineMessage.appendChild(undoSpan);

                        undoSpan.addEventListener('click', () => {
                            input.value = lastOriginalEmail;
                            lastOriginalEmail = null;
                            inlineMessage.innerHTML = '';
                        });
                    });
                } else {
                    if (!lastOriginalEmail) {
                        inlineMessage.innerHTML = '';
                    }
                }
            });

        });
    }
});
