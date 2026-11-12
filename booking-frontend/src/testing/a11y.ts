import axe from 'axe-core';

/**
 * Controlla con axe-core (lo stesso motore di molti strumenti di accessibilità) che un elemento non abbia
 * violazioni WCAG 2.x A/AA: etichette dei campi, nomi dei pulsanti, ruoli ARIA, struttura dei titoli...
 *
 * Il contrasto dei colori è escluso perché nell'ambiente di test (jsdom) non c'è un motore di rendering:
 * si verifica nel browser vero (vedi la verifica con Chromium nel README).
 *
 * @throws un errore con l'elenco delle violazioni, se ce ne sono
 */
export async function expectNoA11yViolations(element: HTMLElement): Promise<void> {
  document.body.appendChild(element); // axe lavora sul documento: l'elemento deve starci dentro

  const results = await axe.run(element, {
    runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'] },
    rules: { 'color-contrast': { enabled: false }, region: { enabled: false } },
  });

  if (results.violations.length) {
    const report = results.violations
      .map((v) => `- ${v.id}: ${v.help}\n    ${v.nodes.map((n) => n.html.slice(0, 120)).join('\n    ')}`)
      .join('\n');
    throw new Error(`Violazioni di accessibilità:\n${report}`);
  }
}
