/* Mandatory shared form attribution. Capture original acquisition; never invent ad names or analytics IDs. */
(() => {
  'use strict';
  const keys = 'fbp fbc gclid gbraid wbraid fbclid ga_client_id session_id utm_source utm_medium utm_campaign utm_adgroup utm_term utm_content landing_page referrer utm_id campaign_name ad_name adset_name campaign_id adset_id ad_id fb_campaign fb_ad fb_adset_name fb_adset_id fb_lead_id city state'.split(' ');
  const aliases = {gaclientid:'ga_client_id',sessionid:'session_id',landingpage:'landing_page',utmsource:'utm_source',utmmedium:'utm_medium',utmcampaign:'utm_campaign',utmadgroup:'utm_adgroup',utmterm:'utm_term',utmcontent:'utm_content',ad_set_name:'adset_name',adset:'adset_name'};
  const storageKey = 'tlit_lead_attribution_v1';
  const clean = value => String(value || '').replace(/[\u0000-\u001f\u007f]/g, '').slice(0, 500);
  const safeURL = value => { try { const url = new URL(value); return /^https?:$/.test(url.protocol) ? url.origin + url.pathname : ''; } catch { return ''; } };
  const params = new URLSearchParams(location.search);
  for (const [alias,key] of Object.entries(aliases)) if (!params.get(key) && params.get(alias)) params.set(key,params.get(alias));
  let saved = {};
  try { saved = JSON.parse(sessionStorage.getItem(storageKey) || '{}') || {}; } catch {}
  const campaignKeys = keys.filter(key => /^(utm_|fb_|campaign_|ad_|adset_)/.test(key) || ['gclid','gbraid','wbraid','fbclid'].includes(key));
  const newCampaign = campaignKeys.some(key => params.get(key));
  const data = Object.fromEntries(keys.map(key => [key, clean(params.get(key) || (newCampaign ? '' : saved[key]))]));
  data.landing_page = safeURL(data.landing_page) || safeURL(location.href);
  data.referrer = safeURL(data.referrer) || safeURL(document.referrer) || 'direct';
  const cookie = name => { try { return decodeURIComponent(document.cookie.split(/;\s*/).find(item => item.startsWith(name + '='))?.slice(name.length + 1) || ''); } catch { return ''; } };
  function collect() {
    for (const [key,name] of [['fbp','_fbp'],['fbc','_fbc']]) data[key] = clean(cookie(name)) || data[key];
    const ga = cookie('_ga').match(/^GA\d+\.\d+\.(\d+\.\d+)$/);
    if (ga) data.ga_client_id = ga[1];
    if (!data.session_id) {
      const name = document.cookie.split(/;\s*/).map(item => item.split('=')[0]).find(name => /^_ga_/.test(name));
      const session = name ? cookie(name).split('.').slice(2).join('.').match(/^s?(\d+)/) : null;
      if (session) data.session_id = session[1];
    }
    try { sessionStorage.setItem(storageKey, JSON.stringify(data)); } catch {}
    return {...data};
  }
  function decorate(form) {
    const tracking = collect();
    for (const key of keys) {
      let field = form.querySelector('input[type="hidden"][name="' + key + '"]');
      if (!field) { field = document.createElement('input'); field.type = 'hidden'; field.name = key; field.className = 'all_params'; form.append(field); }
      field.value = tracking[key];
    }
    return tracking;
  }
  function enhance(root) {
    if (root.matches?.('form')) decorate(root);
    root.querySelectorAll?.('form').forEach(decorate);
  }
  window.TLITAttribution = {keys,collect,decorate};
  collect();
  document.addEventListener('submit', event => { if (event.target.matches('form')) decorate(event.target); }, true);
  document.addEventListener('DOMContentLoaded', () => {
    enhance(document);
    new MutationObserver(records => records.forEach(record => record.addedNodes.forEach(node => { if (node.nodeType === 1) enhance(node); }))).observe(document.body,{childList:true,subtree:true});
  });
})();
