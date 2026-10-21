'use strict';
const pageAssetBase=new URL('.',document.currentScript.src).href;
const modules=[['01–02','Understand the customer','Start with marketing fundamentals, audience research and positioning. Turn research into a clear brief.','An audience and brand brief.','ChatGPT · Perplexity · Google Trends'],['03–04','Build the brand & landing page','Create a WordPress website or landing page with a clear message and next step.','Your brand & landing page starter kit.','WordPress · Elementor · ChatGPT'],['05–06','Help people find the business','Connect keyword research, on-page SEO and technical checks to a useful visibility plan.','An SEO audit and action plan.','Semrush · Search Console · Screaming Frog'],['07–08','Create content with direction','Plan a 30-day campaign, develop creative and use AI to support your process.','Your content calendar and campaign creative.','Canva · CapCut · Claude'],['09–10','Plan ads & measure what matters','Connect campaign goals with targeting, creative, tracking and meaningful reporting.','A campaign and measurement plan.','Google Ads · GA4 · Google Tag Manager'],['11–12','Connect ads, leads & follow-up','Bring ads, CRM, email, WhatsApp and automation into a connected growth system.','Your 90-day AI-enabled growth plan.','Meta Ads · n8n · WhatsApp Business']];
document.querySelector('#module-list').innerHTML=modules.map((m,i)=>`<details name="curriculum" ${i===0?'open':''}><summary><small>WEEKS ${m[0]}</small>${m[1]}</summary><div class="detail-body"><p>${m[2]}</p><p class="detail-output"><b>Your output:</b> ${m[3]}</p><p class="detail-tools">Tools you’ll explore: ${m[4]}</p></div></details>`).join('');
// Keep the visible accordion and FAQ structured data on the same source.
const pageSchema=JSON.parse(document.querySelector('#course-faq-schema').textContent);
const faqs=pageSchema['@graph'].find(entity=>entity['@type']==='FAQPage').mainEntity.map(question=>[question.name,question.acceptedAnswer.text]);
document.querySelector('#faq-list').innerHTML=faqs.map(([q,a])=>`<details name="faq"><summary>${q}</summary><div class="detail-body"><p>${a}</p></div></details>`).join('');
const tools=[['01-chatgpt.webp','ChatGPT'],['03-claude.svg','Claude'],['04-perplexity.svg','Perplexity'],['05-google-trends.webp','Google Trends'],['09-wordpress.svg','WordPress'],['elementor.svg','Elementor'],['semrush.webp','Semrush'],['15-google-search-console.svg','Search Console'],['11-canva.webp','Canva'],['24-capcut.webp','CapCut'],['27-google-ads.svg','Google Ads'],['29-meta-ads-manager.svg','Meta Ads'],['25-google-analytics-4.svg','Google Analytics'],['26-google-tag-manager.svg','Tag Manager'],['33-mailchimp.svg','Mailchimp'],['34-whatsapp-business.svg','WhatsApp Business'],['35-n8n.svg','n8n'],['36-make.svg','Make']];
document.querySelector('#tools-grid').innerHTML=tools.map(([file,name])=>`<div class="tool-tile"><img src="${pageAssetBase}assets/tools/${file}" width="28" height="28" loading="lazy" alt=""><span>${name}</span></div>`).join('');
const reviews=[
  [
    "Rohith Naidu",
    "Traffic & revenue",
    "I applied the strategies I learned in the course, which helped me increase traffic by 60% and generate around ₹20 lakh in revenue. I’m glad I chose this course—it feels like the right decision."
  ],
  [
    "Chandrasekhar",
    "Landing pages & leads",
    "I built a landing page using the conversion optimisation techniques I learned in the course. The approach was simple and practical, and it helped me generate more than 500 leads for a clinic business."
  ],
  [
    "Supriya Chowdary",
    "Audience targeting & ads",
    "The course made audience segmentation and ad creation easy to understand. I used what I learned to create a campaign that achieved a 2.3x return on ad spend (ROAS)."
  ],
  [
    "Rahul Kapoor",
    "Funnels & CRM",
    "The funnel lessons were easy to follow and went into real depth. I learned how funnels work for different businesses and how to connect them to a CRM. I’m glad I took this course."
  ],
  [
    "Nisha Joshi",
    "Projects & interviews",
    "The three projects helped me succeed in interviews. The resume guidance helped me create an ATS-friendly resume, and I started getting around 22–25 calls a day through Naukri."
  ],
  [
    "Vineesh Reddy",
    "AI tools & career skills",
    "We covered 60+ AI tools in the course. I used just 10 of them, and those skills helped me land a job at a large multinational company. I’m thankful to Tech Leads IT for this valuable course."
  ]
];
const reviewPresentation=[
  ['google.png',['increase traffic by 60%','₹20 lakh in revenue']],
  ['facebook.svg',['conversion optimisation','more than 500 leads']],
  ['linkedin.svg',['audience segmentation','2.3x return on ad spend']],
  ['facebook.svg',['funnels work for different businesses','connect them to a CRM']],
  ['linkedin.svg',['succeed in interviews','22–25 calls a day']],
  ['google.png',['60+ AI tools','land a job']]
];
const reviewEscape=text=>text.replace(/[&<>"']/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
const track=document.querySelector('.review-track');
track.innerHTML=reviews.map(([name,area,quote],index)=>{
  const [logo,hooks]=reviewPresentation[index];
  const highlighted=hooks.reduce((text,hook)=>text.replaceAll(reviewEscape(hook),`<mark class="review-hook">${reviewEscape(hook)}</mark>`),reviewEscape(quote));
  return `<article class="review-card"><header class="review-person"><span class="review-logo" aria-hidden="true"><img src="${pageAssetBase}assets/review-platforms/${logo}" width="32" height="32" alt="" decoding="async"></span><div><b>${reviewEscape(name)}</b><small>${reviewEscape(area)}</small></div></header><blockquote>“${highlighted}”</blockquote></article>`;
}).join('');
for(const group of ['curriculum','faq'])document.querySelectorAll(`details[name=${group}]`).forEach(d=>d.addEventListener('toggle',()=>{if(d.open)document.querySelectorAll(`details[name=${group}]`).forEach(other=>{if(other!==d)other.open=false;});}));
const menu=document.querySelector('.menu-toggle'),nav=document.querySelector('#navigation');menu.onclick=()=>{const open=menu.getAttribute('aria-expanded')!=='true';menu.setAttribute('aria-expanded',String(open));nav.classList.toggle('open',open);};nav.addEventListener('click',e=>{if(e.target.closest('a')){nav.classList.remove('open');menu.setAttribute('aria-expanded','false');}});
document.querySelector('[data-open-chat]').onclick=()=>document.querySelector('.chat-launch')?.click();
/* Shared lead-forms.js handles enquiries and brochure requests. */
