// Direct SCM registration: website -> Apps Script -> this private Sheet only.
const SCM_SHEET_ID = '1wSL-AyX-nsetyp3SzLnJ8JxQnF7k7qyUN1njU1ENR-o';
const SCM_TAB_ID = 0;
const SCM_HOST = 'lp.techleadsit.com';
const SCM_OBJECTIVES = [
  'I want to get my first job in finance or ERP.',
  'I want to increase my salary.',
  'I want to switch to a new career.',
  'I want to grow into a consultant or lead role.',
  'I want a skill that keeps my career secure.'
];

function reply_(value) {
  return ContentService.createTextOutput(JSON.stringify(value))
    .setMimeType(ContentService.MimeType.JSON);
}
function fail_(code) { return reply_({success: false, code: code}); }
function hex_(bytes) {
  return bytes.map(b => ('0' + (b & 255).toString(16)).slice(-2)).join('');
}
function privateSheet_() {
  const file = Drive.Files.get(SCM_SHEET_ID, {
    fields: 'permissions(type,view)', includePermissionsForView: 'published'
  });
  if (!Array.isArray(file.permissions) || !file.permissions.length ||
      file.permissions.some(p => p.type === 'anyone' || p.type === 'domain' || p.view === 'published')) {
    throw new Error('Set the Sheet to Restricted and stop any Publish to web publication.');
  }
  const sheet = SpreadsheetApp.openById(SCM_SHEET_ID).getSheetById(SCM_TAB_ID);
  const expected = ['Name', 'Email', 'Mobile', 'Career objective'];
  if (!sheet || JSON.stringify(sheet.getRange(1,1,1,4).getValues()[0]) !== JSON.stringify(expected)) {
    throw new Error('Keep these exact headers in A1:D1 of Sheet1: Name, Email, Mobile, Career objective');
  }
  return sheet;
}
function captchaSecret_() {
  const secret = PropertiesService.getScriptProperties().getProperty('TURNSTILE_SECRET_KEY') || '';
  // Reject Cloudflare's public testing secrets on the live deployment.
  if (!secret || /^[123]x0+AA$/.test(secret)) throw new Error('Set your real TURNSTILE_SECRET_KEY in Script Properties.');
  return secret;
}
// Run once from the editor as the Sheet owner, after adding your Turnstile secret.
// Restricts public link sharing; preserves rows and named team permissions.
function setupPrivateSheet() {
  captchaSecret_();
  DriveApp.getFileById(SCM_SHEET_ID).setSharing(DriveApp.Access.PRIVATE, DriveApp.Permission.NONE);
  privateSheet_();
  const properties = PropertiesService.getScriptProperties();
  if (!properties.getProperty('RATE_SALT')) {
    properties.setProperty('RATE_SALT', Utilities.getUuid() + Utilities.getUuid());
  }
  properties.setProperty('REGISTRATION_ENABLED', 'true');
  console.log('Setup complete. Deploy as a Web app executing as you. Keep the website forms paused until tested.');
}
function pauseRegistration() {
  PropertiesService.getScriptProperties().setProperty('REGISTRATION_ENABLED', 'false');
}
// GET never returns rows or contact details.
function doGet() { return fail_('post_required'); }
function fields_(e) {
  const keys = ['name','email','countryCode','mobile','careerObjective','source','registrationId','website','captchaToken'];
  const b = {};
  for (const key of keys) {
    if (!e.parameters || !Array.isArray(e.parameters[key]) || e.parameters[key].length !== 1 || typeof e.parameters[key][0] !== 'string') return null;
    b[key] = e.parameters[key][0].trim();
  }
  b.email = b.email.toLowerCase();
  b.mobile = b.mobile.replace(/[\s()-]/g, '');
  if (!b.name || b.name.length > 100 || /[\x00-\x1F\x7F]/.test(b.name) ||
      b.email.length > 254 || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(b.email) ||
      !/^\+[1-9]\d{0,3}$/.test(b.countryCode) || !/^\d{6,14}$/.test(b.mobile) ||
      (b.countryCode === '+91' && !/^[6-9]\d{9}$/.test(b.mobile)) ||
      !SCM_OBJECTIVES.includes(b.careerObjective) || !['inline','popup'].includes(b.source) ||
      !/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i.test(b.registrationId) ||
      b.website || !b.captchaToken || b.captchaToken.length > 2048) return null;
  b.mobile = b.countryCode + b.mobile.replace(/^0+/, '');
  return /^\+[1-9]\d{7,14}$/.test(b.mobile) ? b : null;
}
function captchaValid_(token) {
  const result = UrlFetchApp.fetch('https://challenges.cloudflare.com/turnstile/v0/siteverify', {
    method: 'post', payload: {secret: captchaSecret_(), response: token},
    muteHttpExceptions: true, followRedirects: false
  });
  if (result.getResponseCode() !== 200) return false;
  const check = JSON.parse(result.getContentText());
  return check.success === true && check.hostname === SCM_HOST && check.action === 'scm_registration';
}
// Called only while holding ScriptLock. Counters contain salted hashes, not emails.
function rateAllowed_(email) {
  const properties = PropertiesService.getScriptProperties();
  const salt = properties.getProperty('RATE_SALT');
  if (!salt) return false;
  const now = Date.now();
  const all = properties.getProperties();
  for (const key of Object.keys(all)) {
    if (!key.startsWith('scm_rate_')) continue;
    const value = JSON.parse(all[key]);
    if (value.until <= now) properties.deleteProperty(key);
  }
  const emailKey = 'scm_rate_' + hex_(Utilities.computeHmacSha256Signature(email, salt, Utilities.Charset.UTF_8));
  const globalKey = 'scm_rate_global';
  const read = (key, period) => {
    const raw = properties.getProperty(key);
    const value = raw ? JSON.parse(raw) : null;
    return value && value.until > now ? value : {count: 0, until: now + period};
  };
  const global = read(globalKey, 60000), person = read(emailKey, 3600000);
  if (global.count >= 30 || person.count >= 3) return false;
  global.count++; person.count++;
  properties.setProperties({[globalKey]: JSON.stringify(global), [emailKey]: JSON.stringify(person)});
  return true;
}
function doPost(e) {
  try {
    if (PropertiesService.getScriptProperties().getProperty('REGISTRATION_ENABLED') !== 'true') return fail_('paused');
    if (!e || !e.postData || typeof e.postData.contents !== 'string' || e.postData.contents.length > 8192) return fail_('invalid_fields');
    const b = fields_(e);
    if (!b) return fail_('invalid_fields');
    if (!captchaValid_(b.captchaToken)) return fail_('security_check');
    // Private access is checked for every save, including retries.
    const sheet = privateSheet_();
    const lock = LockService.getScriptLock();
    if (!lock.tryLock(10000)) return fail_('busy');
    try {
      const values = [b.name, b.email, b.mobile, b.careerObjective];
      const prefix = 'scm:v2:' + b.registrationId + ':';
      const note = prefix + hex_(Utilities.computeDigest(Utilities.DigestAlgorithm.SHA_256, JSON.stringify(values), Utilities.Charset.UTF_8));
      const last = sheet.getLastRow();
      const existing = last > 1 ? sheet.getRange(2,1,last-1,1).getNotes().flat().find(n => n.startsWith(prefix)) : null;
      if (existing && existing !== note) return fail_('request_conflict');
      if (!existing) {
        if (!rateAllowed_(b.email)) return fail_('rate_limited');
        const cells = values.map(value => ({userEnteredValue: {stringValue: value}}));
        cells[0].note = note;
        // Atomic append: four literal-text cells plus retry ID in a note, no email lookup/update.
        Sheets.Spreadsheets.batchUpdate({requests: [{appendCells: {
          sheetId: SCM_TAB_ID, rows: [{values: cells}], fields: 'userEnteredValue,note'
        }}]}, SCM_SHEET_ID);
      }
      return reply_({success: true, registrationId: b.registrationId});
    } finally { lock.releaseLock(); }
  } catch (_) {
    // No submitted fields, Google responses, or secrets in logs or public errors.
    return fail_('unavailable');
  }
}
