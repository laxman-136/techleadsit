function doPost(e) {
  var lock = LockService.getScriptLock();
  // Wait up to 15 seconds for a lock to prevent concurrent writing issues
  lock.tryLock(15000); 
  
  try {
    var sheet = SpreadsheetApp.getActiveSpreadsheet().getActiveSheet();
    
    // Parse incoming JSON or Form Parameter payloads
    var data;
    if (e.postData && e.postData.contents) {
      try {
        data = JSON.parse(e.postData.contents);
      } catch (err) {
        data = e.parameter;
      }
    } else {
      data = e.parameter;
    }
    
    // Headers mapped in the Google Sheet columns
    var headers = [
      "Timestamp",
      "Full Name",
      "WhatsApp Number",
      "Email Address",
      "Current Status",
      "SQL Experience",
      "Primary Goal",
      "UTM Source",
      "UTM Medium",
      "UTM Campaign",
      "UTM Adgroup",
      "UTM Term",
      "UTM Content",
      "GCLID",
      "FBCLID",
      "Referrer",
      "Landing Page",
      "Session ID"
    ];
    
    // Ensure header row is set up if sheet is empty
    var lastRow = sheet.getLastRow();
    if (lastRow === 0) {
      sheet.appendRow(headers);
    } else {
      var checkHeaders = sheet.getRange(1, 1, 1, headers.length).getValues()[0];
      var isEmpty = checkHeaders.every(function(cell) { return cell === ""; });
      if (isEmpty) {
        sheet.getRange(1, 1, 1, headers.length).setValues([headers]);
      }
    }
    
    // Construct the row data matched to the headers
    var row = [];
    row.push(new Date()); // Timestamp
    row.push(data.name || "");
    row.push(data.phone || "");
    row.push(data.email || "");
    row.push(data.status || "");
    row.push(data.experience || "");
    row.push(data.goal || "");
    row.push(data.utm_source || "");
    row.push(data.utm_medium || "");
    row.push(data.utm_campaign || "");
    row.push(data.utm_adgroup || "");
    row.push(data.utm_term || "");
    row.push(data.utm_content || "");
    row.push(data.gclid || "");
    row.push(data.fbclid || "");
    row.push(data.referrer || "");
    row.push(data.landing_page || "");
    row.push(data.session_id || "");
    
    sheet.appendRow(row);
    
    // Return standard success response
    return ContentService.createTextOutput(JSON.stringify({ "result": "success", "row": sheet.getLastRow() }))
                         .setMimeType(ContentService.MimeType.JSON);
  } catch (error) {
    return ContentService.createTextOutput(JSON.stringify({ "result": "error", "error": error.toString() }))
                         .setMimeType(ContentService.MimeType.JSON);
  } finally {
    lock.releaseLock();
  }
}
