# Lead attribution contract

Visitor fields stay hidden on public forms. The administrator and authorised consultant can read them in the main Conversion Bridge, the private Teams-linked lead view and exports. On counsellor handoff they go to the existing TeleCRM tracking fields; the complete captured tracking data and form answers also appear in `remarks` and the system note. TeleCRM fields were checked against the connected workspace schema on 26 November 2026.

The 16 mandatory keys are `fbp fbc gclid gbraid wbraid fbclid ga_client_id session_id utm_source utm_medium utm_campaign utm_adgroup utm_term utm_content landing_page referrer`.

The shared collector decorates dynamically created forms as well as initial forms. JSON clients must merge `window.TLITAttribution.collect()` into their `tracking` object. URL-encoded clients must copy these fields into the request. The server accepts either a nested tracking object or flat hidden fields. Store the attribution snapshot with the lead, not only the outbound notification.

| Meaning | Name precedence | TeleCRM destination |
| --- | --- | --- |
| Campaign | `campaign_name`, `fb_campaign`, `utm_campaign` | `utmcampaign`, `facebook_campaign` for Meta, `remarks` |
| Ad | `ad_name`, `fb_ad`, `utm_content` | `utmcontent`, `facebook_ad` for Meta, `remarks` |
| Ad set / Google ad group | `adset_name`, `fb_adset_name`, `utm_adgroup` | `utmadgroup`, `facebook_ad_set_name` for Meta, `remarks` |
| Analytics client / session | `ga_client_id`, `session_id` | `gaclientid`, `sessionid`, `remarks` |
| Click and browser IDs | `gclid`, `gbraid`, `wbraid`, `fbclid`, `fbp`, `fbc` | Existing fields (`google_gcl_id` for gclid); `fbc` is retained in Remarks because this workspace has no dedicated fbc field |
| Original entry / referrer | `landing_page`, `referrer` | `landingpage`, `referrer`, `remarks` |

Names and IDs are separate. Advertising URLs must supply the names; a click ID alone cannot provide them. Example Meta URL suffix: `utm_source=facebook&utm_medium=paid_social&utm_campaign={{campaign.name}}&utm_adgroup={{adset.name}}&utm_content={{ad.name}}&campaign_name={{campaign.name}}&adset_name={{adset.name}}&ad_name={{ad.name}}&campaign_id={{campaign.id}}&adset_id={{adset.id}}&ad_id={{ad.id}}`. Google URLs should supply configured campaign/ad group/creative labels through UTM or explicit name parameters; numeric IDs remain IDs.

Preserve `utm_id`, campaign/ad/ad set IDs and existing `fb_*` fields in storage, exports and CRM handoff. Strip query/fragment information from displayed page/referrer URLs. Empty or unavailable fields remain empty in storage and display as “Not recorded”; do not require ad tracking for an organic enquiry. Authentication tokens, OTP codes, webhook secrets and private session hashes must never appear in consultant attribution panels, Teams cards or CRM notes.

Run the offline attribution contract tests and the affected integration tests with all SMS, Teams and TeleCRM requests mocked. GitHub’s `Lead attribution contract` workflow must pass on every change. Making it a required branch-protection status check additionally requires repository administration access.
