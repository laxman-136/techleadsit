const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const path = require('node:path');

const root = path.resolve(__dirname, '../..');
function render(url, method = 'GET') {
  return execFileSync(process.env.SCM_PHP_PATH || 'C:/xampp/php/php.exe',
    ['run_test.php', '--route', url, method], { cwd: root, encoding: 'utf8' });
}

for (const suffix of ['', '/', '?utm_source=google', '/?utm_source=facebook']) {
  const course = render('/oracle-fusion-scm-course-it' + suffix);
  assert.ok(course.includes('Want an IT Career'));
  assert.ok(course.includes('2027-01-21T19:15:00+05:30'));
  assert.ok(course.includes('http://example.com/oracle-fusion-scm-course-it/styles.css?v=1.7.24'));
  assert.ok(course.includes('window.SCM_OTP_ENDPOINT='));
  assert.ok(course.includes('window.SCM_REGISTRATION_ENDPOINT='));
  assert.ok(course.includes('"@type": "Course"'));
  assert.ok(!course.includes('scm-batch-80'));
  assert.equal(render('/oracle-fusion-scm-course-it' + suffix, 'HEAD'), '');

  const event = render('/scm-batch-80' + suffix);
  assert.ok(event.includes('"@type": "Event"'));
  assert.ok(event.includes('2026-12-12T08:00:00+05:30'));
  assert.ok(event.includes('id="inline-demo-form"'));
  assert.ok(event.includes('http://example.com/oracle-fusion-scm-live-demo/app.js?v=13'));
  assert.ok(!event.includes('2027-01-21'));
  assert.ok(!event.includes('window.SCM_REGISTRATION_ENDPOINT='));
  assert.ok(!event.includes('oracle-fusion-scm-course-it'));
  assert.equal(render('/scm-batch-80' + suffix, 'HEAD'), '');
}
console.log('PASS: Separate course/event routes, early canonical handling, independent settings and asset paths, query strings, trailing slashes and HEAD responses.');
