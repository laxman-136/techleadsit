// Deploy as the Sheet owner. Never paste the shared secret into this source file.
const SCM_SHEET_ID = '1wSL-AyX-nsetyp3SzLnJ8JxQnF7k7qyUN1njU1ENR-o';
const SCM_TAB_ID = 0;
const SCM_OBJECTIVES = ['I want to get my first job in finance or ERP.','I want to increase my salary.','I want to switch to a new career.','I want to grow into a consultant or lead role.','I want a skill that keeps my career secure.'];

function response_(value) {
  return ContentService.createTextOutput(JSON.stringify(value)).setMimeType(ContentService.MimeType.JSON);
}
function hex_(bytes) { return bytes.map(b => ('0' + (b & 255).toString(16)).slice(-2)).join(''); }
function equal_(a, b) {
  if (typeof a !== 'string' || a.length !== b.length) return false;
  let diff = 0;
  for (let i = 0; i < b.length; i++) diff |= a.charCodeAt(i) ^ b.charCodeAt(i);
  return diff === 0;
}
function google_(url, options) {
  const result = UrlFetchApp.fetch(url, Object.assign({}, options, {
    headers: {Authorization: 'Bearer ' + ScriptApp.getOAuthToken()},
    muteHttpExceptions: true, followRedirects: false
  }));
  if (result.getResponseCode() !== 200) throw new Error('Google operation failed');
  return JSON.parse(result.getContentText());
}
function assertPrivate_() {
  const file = google_('https://www.googleapis.com/drive/v3/files/' + SCM_SHEET_ID + '?fields=permissions(type,view)&includePermissionsForView=published', {});
  if (!Array.isArray(file.permissions) || !file.permissions.length || file.permissions.some(p => p.type === 'anyone' || p.type === 'domain' || p.view === 'published')) {
    throw new Error('Sheet must be Restricted and not published');
  }
}
// Run once from the Apps Script editor as the owner. No existing rows are changed.
function setupPrivateSheet() {
  DriveApp.getFileById(SCM_SHEET_ID).setSharing(DriveApp.Access.PRIVATE, DriveApp.Permission.NONE);
  assertPrivate_();
  const secret = PropertiesService.getScriptProperties().getProperty('SCM_SHARED_SECRET');
  if (!/^[a-f0-9]{64}$/.test(secret || '')) throw new Error('Set SCM_SHARED_SECRET in Script Properties first');
  const sheet = SpreadsheetApp.openById(SCM_SHEET_ID).getSheetById(SCM_TAB_ID);
  if (!sheet || JSON.stringify(sheet.getRange(1,1,1,4).getValues()[0]) !== JSON.stringify(['Name','Email','Mobile','Career objective'])) throw new Error('Expected four registration headers in Sheet1');
}
function doGet() { return response_({success:false, message:'POST required'}); }
function doPost(e) {
  try {
    if (!e || !e.postData || e.postData.contents.length > 8192) throw new Error('Invalid request');
    const envelope = JSON.parse(e.postData.contents);
    const secret = PropertiesService.getScriptProperties().getProperty('SCM_SHARED_SECRET');
    if (!/^[a-f0-9]{64}$/.test(secret || '') || typeof envelope.payload !== 'string' || !/^\d{10}$/.test(envelope.timestamp || '') || Math.abs(Date.now()/1000 - Number(envelope.timestamp)) > 120) throw new Error('Invalid authentication');
    const signature = hex_(Utilities.computeHmacSha256Signature(envelope.timestamp + '.' + envelope.payload, secret, Utilities.Charset.UTF_8));
    if (!equal_(envelope.signature, signature)) throw new Error('Invalid authentication');
    const b = JSON.parse(envelope.payload);
    if (!b || typeof b.name !== 'string' || !b.name.trim() || b.name.length > 100 || /[\x00-\x1F\x7F]/.test(b.name)
      || typeof b.email !== 'string' || b.email.length > 254 || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(b.email)
      || typeof b.mobile !== 'string' || !/^\+[1-9]\d{7,14}$/.test(b.mobile)
      || !SCM_OBJECTIVES.includes(b.careerObjective) || typeof b.registrationId !== 'string'
      || !/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i.test(b.registrationId)) throw new Error('Invalid fields');
    assertPrivate_(); // Fail closed if broad sharing is enabled again later.
    const lock = LockService.getScriptLock();
    if (!lock.tryLock(10000)) throw new Error('Busy');
    try {
      const sheet = SpreadsheetApp.openById(SCM_SHEET_ID).getSheetById(SCM_TAB_ID);
      if (!sheet || JSON.stringify(sheet.getRange(1,1,1,4).getValues()[0]) !== JSON.stringify(['Name','Email','Mobile','Career objective'])) throw new Error('Invalid headers');
      const last = sheet.getLastRow();
      const prefix = 'scm:v1:' + b.registrationId + ':';
      const note = prefix + hex_(Utilities.computeDigest(Utilities.DigestAlgorithm.SHA_256, envelope.payload, Utilities.Charset.UTF_8));
      // A durable note in column A is written atomically with the four cells.
      // This identifies retries by random request ID, never by another person's email.
      const existing = last > 1 ? sheet.getRange(2,1,last-1,1).getNotes().flat().find(n => n.startsWith(prefix)) : null;
      if (existing && existing !== note) throw new Error('Request ID conflict');
      if (!existing) {
        const values = [b.name, b.email, b.mobile, b.careerObjective].map(value => ({userEnteredValue:{stringValue:value}}));
        values[0].note = note;
        google_('https://sheets.googleapis.com/v4/spreadsheets/' + SCM_SHEET_ID + ':batchUpdate', {
          method:'post', contentType:'application/json',
          payload:JSON.stringify({requests:[{appendCells:{sheetId:SCM_TAB_ID, rows:[{values}], fields:'userEnteredValue,note'}}]})
        });
      }
      return response_({success:true, registrationId:b.registrationId});
    } finally { lock.releaseLock(); }
  } catch (_) {
    // Never include contact fields, credentials or Google error details in responses/logs.
    return response_({success:false, message:'Registration not confirmed'});
  }
}
