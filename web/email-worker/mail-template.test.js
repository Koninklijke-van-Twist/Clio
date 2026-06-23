import assert from 'node:assert/strict';
import test from 'node:test';
import {
  buildClioNotificationEmail,
  buildProjectUploadSuccessNotification,
  escapeHtml,
} from './mail-template.js';

test('escapeHtml encodes unsafe characters', () => {
  assert.equal(escapeHtml('<script>&"\'</script>'), '&lt;script&gt;&amp;&quot;&#39;&lt;/script&gt;');
});

test('buildClioNotificationEmail renders branded HTML with sharepoint link', () => {
  const notification = buildProjectUploadSuccessNotification(
    '153703',
    'VT Groenekan (GRK) opslag',
    'https://example.test/sites/Demo/0001-mail.eml',
  );

  assert.match(notification.plainText, /153703 \(VT Groenekan \(GRK\) opslag\)/);
  assert.match(notification.plainText, /https:\/\/example\.test\/sites\/Demo\/0001-mail\.eml/);
  assert.match(notification.html, /#0f766e/);
  assert.match(notification.html, /Bekijk bestand in SharePoint/);
  assert.match(notification.html, /https:\/\/example\.test\/sites\/Demo\/0001-mail\.eml/);
});

test('buildClioNotificationEmail includes ICT diagnostics block', () => {
  const notification = buildClioNotificationEmail({
    paragraphs: ['Testbericht'],
    diagnosticLines: ['Regel 1', 'Regel 2'],
  });

  assert.match(notification.plainText, /--- ICT diagnose ---/);
  assert.match(notification.html, /ICT diagnose/);
  assert.match(notification.html, /Regel 1/);
});
