/* HCM-compatible attribution. No fabricated analytics IDs or contact details in GTM. */
(()=>{
 const config=window.COURSE_ENQUIRY_CONFIG||{},key='tlit_dm_attribution_v1';
 const keys='utm_source utm_medium utm_campaign utm_adgroup utm_term utm_content gclid gbraid wbraid fbclid fb_ad fb_campaign fb_adset_name fb_adset_id fb_lead_id adset_id ad_id'.split(' ');
 const clean=v=>String(v||'').replace(/[\u0000-\u001f\u007f]/g,'').slice(0,250);
 const read=()=>{try{return JSON.parse(sessionStorage.getItem(key)||'{}');}catch{return {};}};
 const params=new URLSearchParams(location.search),hasCampaign=keys.some(k=>params.has(k));
 const data=hasCampaign?{}:read();keys.forEach(k=>{data[k]=clean(hasCampaign?params.get(k):data[k]);});
 const safeUrl=v=>{try{const u=new URL(v);return /^https?:$/.test(u.protocol)?u.origin+u.pathname:'';}catch{return '';}};
 const cookie=n=>{try{return decodeURIComponent(document.cookie.split('; ').find(c=>c.startsWith(n+'='))?.slice(n.length+1)||'');}catch{return '';}};
 data.landing_page=safeUrl(location.href);data.referrer=data.referrer||safeUrl(document.referrer)||'direct';
 data.session_id=data.session_id||('dm-'+(crypto.randomUUID?.()||Date.now().toString(36)+Math.random().toString(36).slice(2)));
 let geoStarted=false;
 function collect(){const out={...data};out.fbp=clean(cookie('_fbp'));out.fbc=clean(cookie('_fbc'));const ga=cookie('_ga').split('.');out.ga_client_id=ga.length>=4?clean(ga.slice(-2).join('.')):'';out.city=clean(data.city);out.state=clean(data.state);document.querySelectorAll('#conversation-form input.all_params').forEach(i=>i.value=out[i.name]||'');return out;}
 function save(){try{sessionStorage.setItem(key,JSON.stringify(data));}catch{}collect();}
 async function startGeo(){if(geoStarted||config.mode!=='live'||!config.geoEnabled)return;geoStarted=true;const controller=new AbortController(),timer=setTimeout(()=>controller.abort(),2500);try{const r=await fetch('https://ipapi.co/json/',{signal:controller.signal,referrerPolicy:'no-referrer'});if(r.ok){const g=await r.json();if(!g.error){data.city=clean(g.city);data.state=clean(g.region);save();}}}catch{}finally{clearTimeout(timer);}}
 window.courseTracking={collect,startGeo};save();
})();
