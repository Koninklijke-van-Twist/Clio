import assert from 'node:assert/strict';
import test from 'node:test';
import {
  buildClioEmailThreadUrl,
  buildClioNotificationEmail,
  buildNotificationActionLinks,
  buildProjectUploadSuccessNotification,
  escapeHtml,
} from './mail-template.js';

test('escapeHtml encodes unsafe characters', () => {
  assert.equal(escapeHtml('<script>&"\'</script>'), '&lt;script&gt;&amp;&quot;&#39;&lt;/script&gt;');
});

test('buildClioEmailThreadUrl builds emails page link', () => {
  const url = buildClioEmailThreadUrl('https://sleutels.kvt.nl/clio/', '153703~chain@example.test');
  assert.equal(url, 'https://sleutels.kvt.nl/clio/index.php?page=emails&thread=153703%7Echain%40example.test');
});

test('buildNotificationActionLinks orders Clio before SharePoint', () => {
  assert.deepEqual(buildNotificationActionLinks({
    clioUrl: 'https://example.test/clio',
    sharePointUrl: 'https://example.test/sharepoint/file.eml',
  }), [
    { label: 'Bekijk op Clio', url: 'https://example.test/clio' },
    { label: 'Bekijk op SharePoint', url: 'https://example.test/sharepoint/file.eml' },
  ]);
});

test('buildClioNotificationEmail renders action buttons without raw URLs in HTML', () => {
  const notification = buildProjectUploadSuccessNotification(
    '153703',
    'VT Groenekan (GRK) opslag',
    'https://example.test/sites/Demo/0001-mail.eml',
    'https://sleutels.kvt.nl/clio/index.php?page=emails&thread=153703~chain',
  );

  assert.match(notification.plainText, /Bekijk op Clio: https:\/\/sleutels\.kvt\.nl\/clio/);
  assert.match(notification.plainText, /Bekijk op SharePoint: https:\/\/example\.test\/sites\/Demo\/0001-mail\.eml/);
  assert.match(notification.html, /Bekijk op Clio/);
  assert.match(notification.html, /Bekijk op SharePoint/);
  assert.doesNotMatch(notification.html, /word-break:break-all/);
});

test('buildClioNotificationEmail includes ICT diagnostics block', () => {
  const notification = buildClioNotificationEmail({
    paragraphs: ['Testbericht'],
    clioUrl: 'https://example.test/clio',
    diagnosticLines: ['Regel 1', 'Regel 2'],
  });

  assert.match(notification.plainText, /--- ICT diagnose ---/);
  assert.match(notification.html, /ICT diagnose/);
  assert.match(notification.html, /Regel 1/);
  assert.match(notification.html, /Bekijk op Clio/);
});
