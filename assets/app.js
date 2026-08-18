document.addEventListener('click',e=>{const b=e.target.closest('[data-confirm]');if(b&&!confirm(b.dataset.confirm))e.preventDefault();});
function copyText(id){const el=document.getElementById(id); if(!el)return; navigator.clipboard.writeText(el.value||el.textContent).then(()=>alert('Copied.'));}
document.querySelectorAll('.protected-form input[name="js_token"]').forEach(input=>input.value='ready');
const navButton=document.querySelector('.nav-toggle'),nav=document.getElementById('site-nav');if(navButton&&nav){navButton.addEventListener('click',()=>{const open=nav.classList.toggle('open');navButton.setAttribute('aria-expanded',String(open));});}
const themeButton=document.querySelector('.theme-toggle');if(themeButton){const sync=()=>themeButton.setAttribute('aria-pressed',String(document.documentElement.dataset.theme==='dark'));sync();themeButton.addEventListener('click',()=>{const next=document.documentElement.dataset.theme==='dark'?'light':'dark';document.documentElement.dataset.theme=next;try{localStorage.setItem('ign_theme',next)}catch(e){}sync();});}
const cookie=document.querySelector('.cookie-banner');if(cookie){let choice='';try{choice=localStorage.getItem('ign_cookie_choice')||''}catch(e){}if(!choice)cookie.hidden=false;cookie.addEventListener('click',e=>{const button=e.target.closest('[data-cookie]');if(!button)return;try{localStorage.setItem('ign_cookie_choice',button.dataset.cookie)}catch(err){}cookie.hidden=true;const live=document.querySelector('.sr-live');if(live)live.textContent='Cookie choice saved';});}
const countryChoice=document.getElementById('countryChoice'),currencyChoice=document.getElementById('currencyChoice'),customCurrencyFields=document.getElementById('customCurrencyFields');if(countryChoice&&currencyChoice&&customCurrencyFields){let countryMap={};try{countryMap=JSON.parse(countryChoice.dataset.currencyMap||'{}')}catch(e){}try{const names=new Intl.DisplayNames([document.documentElement.lang||'en'],{type:'region'});[...countryChoice.options].forEach(option=>{if(option.value!=='OTHER')option.textContent=`${names.of(option.value)||option.value} — ${option.value}`})}catch(e){}const syncCurrencyFields=()=>{customCurrencyFields.hidden=currencyChoice.value!=='OTHER';customCurrencyFields.querySelectorAll('input,select').forEach(field=>field.disabled=customCurrencyFields.hidden)};countryChoice.addEventListener('change',()=>{const code=countryMap[countryChoice.value];if(code&&[...currencyChoice.options].some(option=>option.value===code)){currencyChoice.value=code;syncCurrencyFields()}});currencyChoice.addEventListener('change',syncCurrencyFields);syncCurrencyFields();}

/* Live document builder: dynamic line items + running totals (progressive enhancement) */
(function(){
  const b=document.querySelector('.doc-builder'); if(!b) return;
  const sym=b.dataset.symbol||'', pos=b.dataset.pos||'before', dec=Math.max(0,Math.min(4,parseInt(b.dataset.dec||'2',10)));
  const money=n=>{const v=(isFinite(n)?n:0).toFixed(dec); return pos==='after'?(v+' '+sym):(sym+v);};
  const items=document.getElementById('lineItems');
  const rowInner='<input class="li-desc" name="description[]" placeholder="Description of work or item">'+
    '<input class="li-qty" type="number" step="0.01" min="0" name="qty[]" value="1" aria-label="Quantity">'+
    '<input class="li-price" type="number" step="0.01" min="0" name="unit_price[]" placeholder="0.00" aria-label="Unit price">'+
    '<span class="li-total" aria-hidden="true">'+money(0)+'</span>'+
    '<button type="button" class="li-remove" aria-label="Remove line">\u2715</button>';
  const set=(id,v)=>{const el=document.getElementById(id); if(el) el.textContent=v;};
  function recalc(){
    let sub=0;
    items.querySelectorAll('.line-item').forEach(r=>{
      const q=parseFloat(r.querySelector('.li-qty').value)||0, p=parseFloat(r.querySelector('.li-price').value)||0, t=q*p;
      const cell=r.querySelector('.li-total'); if(cell) cell.textContent=money(t); sub+=t;
    });
    const tr=parseFloat((document.getElementById('taxRate')||{}).value)||0, tax=sub*tr/100;
    set('sumSubtotal',money(sub)); set('sumTax',money(tax)); set('sumTotal',money(sub+tax));
  }
  function addRow(){const d=document.createElement('div'); d.className='line-item'; d.innerHTML=rowInner; items.appendChild(d);}
  const add=document.getElementById('addLine'); if(add) add.addEventListener('click',()=>{addRow(); recalc();});
  b.addEventListener('input',recalc);
  b.addEventListener('click',e=>{const rm=e.target.closest('.li-remove'); if(!rm) return;
    const rows=items.querySelectorAll('.line-item');
    if(rows.length>1){ rm.closest('.line-item').remove(); }
    else { rm.closest('.line-item').querySelectorAll('input').forEach(i=>{ i.value=i.classList.contains('li-qty')?'1':''; }); }
    recalc();
  });
  const sel=document.getElementById('custSel'), nc=document.getElementById('newCust');
  if(sel&&nc){ const sync=()=>{ nc.hidden=(sel.value!=='new'); }; sel.addEventListener('change',sync); sync(); }
  recalc();
})();

/* Registration: turn 2-letter country codes into full names and preview the currency */
(function(){
  const c=document.getElementById('countryChoice');
  const hint=document.getElementById('currencyHint');
  if(!c||!hint||document.getElementById('currencyChoice')) return; // settings page handled separately
  let map={}; try{ map=JSON.parse(c.dataset.currencyMap||'{}'); }catch(e){}
  let names=null; try{ names=new Intl.DisplayNames([document.documentElement.lang||'en'],{type:'region'}); }catch(e){}
  if(names){ [...c.options].forEach(o=>{ if(o.value && o.value.length===2){ o.textContent=(names.of(o.value)||o.value)+' \u2014 '+o.value; } }); }
  const base=hint.textContent;
  c.addEventListener('change',()=>{ const cur=map[c.value]; hint.textContent=cur?('Currency: '+cur+' \u00b7 set from your country, editable in Settings.'):base; });
})();

/* ---- Free document generator (public front door): client-side, local-first, download gate ---- */
(function(){
  const root=document.getElementById('invgen'); if(!root) return;
  const esc=s=>String(s==null?'':s).replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
  const nl=s=>esc(s).replace(/\n/g,'<br>');
  let CUR={s:'$',p:'before',d:2};
  let currencies={},countries={},logoData='';
  try{currencies=JSON.parse(root.dataset.currencies||'{}');}catch(e){}
  try{countries=JSON.parse(root.dataset.countries||'{}');}catch(e){}
  const free=parseInt(root.dataset.free||'3',10);
  const $=id=>document.getElementById(id);
  const val=id=>{const el=$(id);return el?el.value:'';};
  const num=id=>parseFloat(val(id))||0;
  // All currency held in integer minor units; formatting delegated to the money helper.
  const M=()=>window.IGN_Money;
  const money=minor=>M()?M().format(minor|0,CUR.s,CUR.p,CUR.d):((minor/Math.pow(10,CUR.d)).toFixed(CUR.d));
  const toMinor=v=>M()?M().parse(v,CUR.d):Math.round((parseFloat(v)||0)*Math.pow(10,CUR.d));

  /* Document definitions: title, number prefix, and which fields apply */
  const DOCS={
    'invoice':{title:'INVOICE',prefix:'INV-',due:true,to:'Bill to',from:'From'},
    'tax-invoice':{title:'TAX INVOICE',prefix:'INV-',due:true,to:'Bill to',from:'From',requireTax:true},
    'proforma-invoice':{title:'PROFORMA INVOICE',prefix:'PRO-',due:false,valid:true,to:'Bill to',from:'From',notice:'This is a proforma invoice and is not a tax invoice.'},
    'quote':{title:'QUOTE',prefix:'Q-',due:false,valid:true,to:'Quote for',from:'From'},
    'estimate':{title:'ESTIMATE',prefix:'EST-',due:false,valid:true,to:'Estimate for',from:'From',notice:'This is an estimate. The final amount may vary.'},
    'credit-note':{title:'CREDIT NOTE',prefix:'CN-',due:false,to:'Credit to',from:'From',original:true},
    'receipt':{title:'RECEIPT',prefix:'REC-',due:false,to:'Received from',from:'From',original:true,paidLabel:true},
    'purchase-order':{title:'PURCHASE ORDER',prefix:'PO-',due:false,deliver:true,to:'Supplier',from:'Buyer'},
    'delivery-note':{title:'DELIVERY NOTE',prefix:'DN-',due:false,deliver:true,noPrice:true,to:'Deliver to',from:'From',sign:true},
    'statement':{title:'STATEMENT OF ACCOUNT',prefix:'ST-',due:false,to:'Account for',from:'From'},
    'commercial-invoice':{title:'COMMERCIAL INVOICE',prefix:'CI-',due:true,deliver:true,to:'Importer',from:'Exporter',requireTax:false},
    'debit-note':{title:'DEBIT NOTE',prefix:'DB-',due:false,to:'Debit to',from:'From',original:true},
    'progress-invoice':{title:'PROGRESS INVOICE',prefix:'INV-',due:true,to:'Bill to',from:'From'},
    'final-invoice':{title:'FINAL INVOICE',prefix:'INV-',due:true,to:'Bill to',from:'From'},
    'deposit-invoice':{title:'DEPOSIT INVOICE',prefix:'DEP-',due:true,to:'Bill to',from:'From',notice:'This deposit invoice requests an upfront payment. This tool does not process payments.'},
    'sales-order':{title:'SALES ORDER',prefix:'SO-',due:false,deliver:true,to:'Customer',from:'From'},
    'work-order':{title:'WORK ORDER',prefix:'WO-',due:false,deliver:true,to:'Customer / site',from:'From',sign:true},
    'timesheet':{title:'TIMESHEET',prefix:'TS-',due:false,to:'Client / project',from:'From',sign:true},
    'expense-report':{title:'EXPENSE REPORT',prefix:'EXP-',due:false,to:'Submitted to',from:'From',sign:true},
    'packing-slip':{title:'PACKING SLIP',prefix:'PS-',due:false,deliver:true,noPrice:true,to:'Deliver to',from:'From'},
    'payment-reminder':{title:'PAYMENT REMINDER',prefix:'REM-',due:false,to:'To',from:'From',original:true,noItems:true},
    'account-balance-letter':{title:'ACCOUNT BALANCE',prefix:'ABL-',due:false,to:'To',from:'From',noItems:true},
    'completion-certificate':{title:'COMPLETION CERTIFICATE',prefix:'CC-',due:false,to:'Customer / site',from:'From',sign:true,noPrice:true},
    'goods-received-note':{title:'GOODS RECEIVED NOTE',prefix:'GRN-',due:false,noPrice:true,to:'Supplier',from:'From',original:true,sign:true},
    'return-note':{title:'RETURN NOTE',prefix:'RN-',due:false,to:'Customer',from:'From',original:true},
    'waybill':{title:'WAYBILL',prefix:'WB-',due:false,deliver:true,noPrice:true,to:'Receiver',from:'Sender',sign:true}
  };
  /* Document-label translations (labels on the generated document only) */
  const T={
    en:{subtotal:'Subtotal',tax:'Tax',discount:'Discount',shipping:'Shipping',total:'Total',deposit:'Deposit paid',balance:'Balance due',issue:'Issue',due:'Due',valid:'Valid until',qty:'Qty',price:'Unit price',amount:'Amount',description:'Description',notes:'Notes',payment:'Payment details',number:'No.',po:'PO / Ref',status:'Status',signature:'Signature'},
    fr:{subtotal:'Sous-total',tax:'TVA',discount:'Remise',shipping:'Livraison',total:'Total',deposit:'Acompte versé',balance:'Solde dû',issue:'Émis',due:'Échéance',valid:'Valable jusqu’au',qty:'Qté',price:'Prix unitaire',amount:'Montant',description:'Description',notes:'Notes',payment:'Détails de paiement',number:'N°',po:'Réf.',status:'Statut',signature:'Signature'},
    es:{subtotal:'Subtotal',tax:'Impuesto',discount:'Descuento',shipping:'Envío',total:'Total',deposit:'Depósito pagado',balance:'Saldo pendiente',issue:'Emisión',due:'Vencimiento',valid:'Válido hasta',qty:'Cant.',price:'Precio unitario',amount:'Importe',description:'Descripción',notes:'Notas',payment:'Datos de pago',number:'N.º',po:'Ref.',status:'Estado',signature:'Firma'},
    de:{subtotal:'Zwischensumme',tax:'Steuer',discount:'Rabatt',shipping:'Versand',total:'Gesamt',deposit:'Anzahlung',balance:'Restbetrag',issue:'Datum',due:'Fällig',valid:'Gültig bis',qty:'Menge',price:'Einzelpreis',amount:'Betrag',description:'Beschreibung',notes:'Hinweise',payment:'Zahlungsdetails',number:'Nr.',po:'Ref.',status:'Status',signature:'Unterschrift'},
    it:{subtotal:'Subtotale',tax:'Imposta',discount:'Sconto',shipping:'Spedizione',total:'Totale',deposit:'Acconto versato',balance:'Saldo dovuto',issue:'Data',due:'Scadenza',valid:'Valido fino al',qty:'Qtà',price:'Prezzo unitario',amount:'Importo',description:'Descrizione',notes:'Note',payment:'Dettagli di pagamento',number:'N.',po:'Rif.',status:'Stato',signature:'Firma'},
    pt:{subtotal:'Subtotal',tax:'Imposto',discount:'Desconto',shipping:'Envio',total:'Total',deposit:'Depósito pago',balance:'Saldo devido',issue:'Emissão',due:'Vencimento',valid:'Válido até',qty:'Qtd',price:'Preço unitário',amount:'Valor',description:'Descrição',notes:'Notas',payment:'Dados de pagamento',number:'N.º',po:'Ref.',status:'Estado',signature:'Assinatura'}
  };
  const tr=k=>{const l=val('g_doclang')||'en';return (T[l]&&T[l][k])||T.en[k]||k;};

  // Populate currency + country selects
  const curSel=$('g_currency'), countrySel=$('g_country');
  Object.keys(currencies).sort().forEach(code=>{const o=document.createElement('option');o.value=code;o.textContent=code+' \u2014 '+currencies[code].n;curSel.appendChild(o);});
  let names=null; try{names=new Intl.DisplayNames([document.documentElement.lang||'en'],{type:'region'});}catch(e){}
  Object.keys(countries).sort((a,b)=>{const an=names?names.of(a)||a:a,bn=names?names.of(b)||b:b;return an.localeCompare(bn);}).forEach(cc=>{const o=document.createElement('option');o.value=cc;o.textContent=(names?(names.of(cc)||cc):cc);countrySel.appendChild(o);});
  const setCurrency=(code,skipRender)=>{const c=currencies[code]; if(!c) return; CUR={s:c.s,p:c.p,d:c.d}; curSel.value=code; if(!skipRender)render();};
  setCurrency(root.dataset.default||'USD',true);
  countrySel.addEventListener('change',()=>{const cur=countries[countrySel.value]; if(cur) setCurrency(cur);});
  curSel.addEventListener('change',()=>setCurrency(curSel.value));

  // Dates default
  const today=new Date(),due=new Date(Date.now()+14*864e5);
  const iso=d=>d.toISOString().slice(0,10);
  if($('g_issue'))$('g_issue').value=iso(today);
  if($('g_due'))$('g_due').value=iso(due);

  // Logo (client-side only)
  function refreshLogoUI(){
    if(window.__ignSyncOptions) setTimeout(window.__ignSyncOptions,0);const t=$('logoThumb'),rm=$('logoRemove');
    if(t){ if(logoData){t.src=logoData;t.hidden=false;} else {t.removeAttribute('src');t.hidden=true;} }
    if(rm) rm.hidden=!logoData;}
  // SVG cannot be embedded in a PDF by the PDF engine, so any SVG logo is rasterised to a
  // PNG here, once, on upload. Everything downstream then has a format it can always use.
  function svgToPng(dataUrl){
    return new Promise((resolve,reject)=>{
      const img=new Image();
      img.onload=()=>{
        const scale=Math.min(4, Math.max(2, 600/Math.max(img.width||1,1)));
        const w=Math.max(1,Math.round((img.width||300)*scale));
        const h=Math.max(1,Math.round((img.height||150)*scale));
        const c=document.createElement('canvas');c.width=w;c.height=h;
        const ctx=c.getContext('2d');
        ctx.clearRect(0,0,w,h);
        ctx.drawImage(img,0,0,w,h);
        try{resolve(c.toDataURL('image/png'));}catch(e){reject(e);}
      };
      img.onerror=()=>reject(new Error('Could not read that SVG'));
      img.src=dataUrl;
    });
  }
  $('g_logo').addEventListener('change',e=>{
    const f=e.target.files&&e.target.files[0];
    if(!f){logoData='';refreshLogoUI();render();return;}
    if(f.size>1048576){alert('Logo must be 1 MB or smaller.');e.target.value='';return;}
    const r=new FileReader();
    r.onload=()=>{
      const raw=r.result;
      const isSvg=(f.type==='image/svg+xml')||/^data:image\/svg\+xml/i.test(String(raw));
      if(isSvg){
        svgToPng(raw).then(png=>{logoData=png;refreshLogoUI();render();try{saveDraft();}catch(err){}})
          .catch(()=>{alert('That SVG could not be converted. Please upload a PNG or JPG logo instead.');e.target.value='';logoData='';refreshLogoUI();render();});
      }else{
        logoData=raw;refreshLogoUI();render();try{saveDraft();}catch(err){}
      }
    };
    r.readAsDataURL(f);
  });
  { const rm=$('logoRemove'); if(rm) rm.addEventListener('click',()=>{logoData='';const inp=$('g_logo');if(inp)inp.value='';refreshLogoUI();render();try{saveDraft();}catch(e){}announce('Logo removed.');}); }

  // Advanced only: every field is always available.
  function setMode(){root.classList.add('mode-advanced');root.classList.remove('mode-simple');}

  // Delivery-address toggle
  const same=$('g_sameaddr'), delWrap=$('deliverWrap');
  if(same)same.addEventListener('change',()=>{delWrap.hidden=same.checked;render();});

  // Document type behaviour
  function currentDoc(){return DOCS[val('g_doctype')]||DOCS['invoice'];}
  function applyDoc(){
    const d=currentDoc();
    // headings
    const setTxt=(role,txt)=>{const el=root.querySelector('[data-role="'+role+'"]');if(el)el.textContent=txt;};
    setTxt('from-heading',d.from==='Buyer'?'Buyer details':'Your business');
    setTxt('to-heading',d.to);
    setTxt('number-label',d.title.charAt(0)+d.title.slice(1).toLowerCase()+' '+tr('number'));
    // number prefix if still default-looking
    const numEl=$('g_number');if(numEl&&/^[A-Z]{1,4}-?\d*$/i.test(numEl.value)){numEl.value=d.prefix+'0001';}
    // due vs valid
    const dueField=root.querySelector('[data-role="due-field"]');
    if(dueField){dueField.hidden=!(d.due||d.valid);root.querySelector('[data-role="due-label"]').textContent=d.valid?tr('valid'):tr('due');}
    // original / reason (credit note, receipt)
    const origF=root.querySelector('[data-role="original-field"]'),reasonF=root.querySelector('[data-role="reason-field"]');
    if(origF)origF.hidden=!d.original;
    if(reasonF)reasonF.hidden=!(val('g_doctype')==='credit-note');
    if(origF&&d.paidLabel)root.querySelector('[data-role="original-label"]').textContent='Original invoice number';
    const sig=$('g_showsig');
    if(sig&&!sig.dataset.touched){sig.checked=!!d.sign;}
    render();
  }
  { const sig=$('g_showsig'); if(sig) sig.addEventListener('change',()=>{sig.dataset.touched='1';}); }
  $('g_doctype').addEventListener('change',applyDoc);
  $('g_doclang').addEventListener('change',render);

  // Preselect doc from ?doc= (SEO deep links)
  try{const p=new URLSearchParams(location.search).get('doc');if(p&&DOCS[p]){$('g_doctype').value=p;}}catch(e){}

  // Line items
  const items=$('genItems');
  const rowInner='<label class="li-f li-f-desc"><span>Description</span><input class="li-desc" placeholder="Description of work or item"></label><label class="li-f li-f-qty"><span>Qty</span><input class="li-qty" type="number" step="0.01" min="0" value="1" aria-label="Quantity"></label><label class="li-f li-f-price"><span>Unit price</span><input class="li-price" type="number" step="0.01" min="0" placeholder="0.00" aria-label="Unit price"></label><label class="li-f li-f-tax"><span>Tax %</span><input class="li-tax" type="number" step="0.01" min="0" placeholder="" aria-label="Line tax percent"></label><span class="li-total" aria-hidden="true">\u2014</span><button type="button" class="li-remove" aria-label="Remove line">\u2715</button>';
  $('genAddLine').addEventListener('click',()=>{const d=document.createElement('div');d.className='line-item';d.innerHTML=rowInner;items.appendChild(d);render();});
  root.addEventListener('click',e=>{const rm=e.target.closest('.li-remove'); if(!rm) return; const rows=items.querySelectorAll('.line-item'); if(rows.length>1){const node=rm.closest('.line-item');lastRemoved={node:node,index:Array.prototype.indexOf.call(items.children,node)};node.remove();showUndo();} else rm.closest('.line-item').querySelectorAll('input').forEach(i=>{i.value=i.classList.contains('li-qty')?'1':'';}); render();});

  function lineData(){
    const out=[];const defRate=num('g_taxrate');const incl=$('g_taxincl')&&$('g_taxincl').checked;
    items.querySelectorAll('.line-item').forEach(r=>{
      const desc=r.querySelector('.li-desc').value.trim();
      const q=parseFloat(r.querySelector('.li-qty').value)||0;
      const priceMinor=toMinor(r.querySelector('.li-price').value);
      const lt=r.querySelector('.li-tax');
      const lineRate=(lt&&lt.value!=='')?(parseFloat(lt.value)||0):defRate;
      const grossMinor=M()?M().mulQty(priceMinor,q):Math.round(priceMinor*q);
      let netMinor,taxMinor;
      if(incl&&lineRate>0){
        taxMinor=M()?M().inclusiveTax(grossMinor,lineRate):0;
        netMinor=grossMinor-taxMinor;
      }else{
        netMinor=grossMinor;
        taxMinor=M()?M().pct(netMinor,lineRate):0;
      }
      r.querySelector('.li-total').textContent=money(grossMinor);
      if(desc||priceMinor)out.push({desc,q,priceMinor,netMinor,taxMinor,grossMinor});
    });
    return out;
  }

  // ================= DIRECT EDITING =================
  // The document IS the form. Each editable region is a contenteditable span bound to a
  // hidden field, so every existing feature (PDF export, autosave, history, totals) keeps
  // reading the same state it always did. Nothing downstream had to change.
  function ed(field, value, placeholder, cls){
    // Every device, not just touch. V64 gated this behind a coarse-pointer check
    // on the reasoning that a keyboard beats a calendar; that was my call, not a
    // requirement, and it meant desktop never got a picker at all.
    const pickerMode = !!cls && cls.indexOf('ed-date') >= 0;
    if(pickerMode){
      // A REAL date input, transparent, laid over the printed date. Tapping it is
      // a direct interaction with a date input, so every browser opens its own
      // picker. V63 called showPicker() instead, which throws InvalidStateError
      // or NotAllowedError depending on the browser, and the focus() fallback is
      // ignored by iOS when it comes from a tap on a different element - so
      // nothing happened at all. This depends on no API beyond input[type=date].
      return '<span class="ed'+(cls?(' '+cls):'')+' ed-date-wrap" data-bind="'+field+'">'
        + '<span class="ed-date-text">'+esc(value||esc(placeholder||''))+'</span>'
        + '<input type="date" class="ed-date-input" value="'+esc(value||'')+'"'
        + ' data-bind="'+field+'" aria-label="Change date">'
        + '</span>';
    }
    return '<span class="ed'+(cls?(' '+cls):'')+'" contenteditable="true" spellcheck="false"'
      + ' data-bind="'+field+'" data-ph="'+esc(placeholder||'')+'">'+esc(value||'')+'</span>';
  }
  // Multi-line editable (address, notes). Newlines survive via <br> on the way out.
  function edML(field, value, placeholder, cls){
    return '<span class="ed ed-ml'+(cls?(' '+cls):'')+'" contenteditable="true" spellcheck="false"'
      + ' data-bind="'+field+'" data-ph="'+esc(placeholder||'')+'">'+nl(value||'')+'</span>';
  }
  // A line-item cell, addressed by row index and key rather than a field id.
  function edLine(i, key, value, placeholder, cls){
    return '<span class="ed'+(cls?(' '+cls):'')+'" contenteditable="true" spellcheck="false"'
      + ' data-line="'+i+'" data-key="'+key+'" data-ph="'+esc(placeholder||'')+'">'+esc(value||'')+'</span>';
  }
  function edText(el){
    // Convert the edited HTML back to plain text, preserving line breaks.
    const html=el.innerHTML.replace(/<div>/gi,'\n').replace(/<br\s*\/?>/gi,'\n').replace(/<\/div>/gi,'');
    const tmp=document.createElement('textarea'); tmp.innerHTML=html.replace(/<[^>]+>/g,'');
    return tmp.value.replace(/\u00a0/g,' ').replace(/\n{3,}/g,'\n\n').trim();
  }

  function render(){
    const d=currentDoc();
    const rows=lineData();
    let sub=0,taxTotal=0; rows.forEach(r=>{sub+=r.netMinor;taxTotal+=r.taxMinor;});
    // discount
    const dv=val('g_discval'),dt=val('g_disctype');
    const discount=(dt==='fixed'?toMinor(dv):(M()?M().pct(sub,dv):0));
    const shipping=toMinor(val('g_shipping'));
    const deposit=toMinor(val('g_deposit'));
    const netAfterDisc=Math.max(0,sub-discount);
    const total=netAfterDisc+taxTotal+shipping;
    const balance=Math.max(0,total-deposit);
    const taxLabel=val('g_taxname').trim()||tr('tax');
    const biz=val('g_bizname').trim()||'Your business';
    const contact=[val('g_bizemail').trim(),val('g_bizphone').trim(),val('g_bizaddr').trim(),(val('g_biztax').trim())?('Tax reg: '+val('g_biztax').trim()):''].filter(Boolean).join('\n');
    const client=[val('g_clientname').trim(),val('g_clientemail').trim(),val('g_clientaddr').trim()].filter(Boolean).join('\n');
    const deliver=($('g_sameaddr')&&!$('g_sameaddr').checked)?val('g_deliveraddr').trim():'';
    const tmpl=root.dataset.template||'modern';

    let html='<div class="doc doc-tmpl-'+tmpl+'">';
    html+='<div class="doc-head"><div>';
    if(logoData) html+='<img class="doc-logo" src="'+logoData+'" alt="Business logo">';
    html+='<div class="doc-brandname">'+ed('g_bizname',val('g_bizname').trim(),'Who is this from?')+'</div>';
    html+='<h1>'+esc(d.title)+'</h1><strong>'+ed('g_number',val('g_number')||d.prefix+'0001','#')+'</strong>';
    html+='<p class="muted doc-contact">'
      + edML('g_bizaddr',val('g_bizaddr').trim(),'Address')
      + '<br>'+ed('g_bizemail',val('g_bizemail').trim(),'Email')
      + '<br>'+ed('g_bizphone',val('g_bizphone').trim(),'Phone')
      + (val('g_biztax').trim()||d.requireTax ? ('<br>Tax reg: '+ed('g_biztax',val('g_biztax').trim(),'VAT / GST number')) : '')
      + '</p>';
    html+='</div><div><strong>'+esc(d.to)+'</strong>';
    html+='<p class="muted doc-client">'
      + ed('g_clientname',val('g_clientname').trim(),'Who is this to?')
      + '<br>'+edML('g_clientaddr',val('g_clientaddr').trim(),'Address')
      + '<br>'+ed('g_clientemail',val('g_clientemail').trim(),'Email')
      + '</p>';
    if(deliver||($('g_sameaddr')&&!$('g_sameaddr').checked)) html+='<strong>Deliver to</strong><p class="muted">'+edML('g_deliveraddr',deliver,'Delivery address')+'</p>';
    html+='<strong>'+tr('issue')+':</strong> '+ed('g_issue',val('g_issue')||'','YYYY-MM-DD','ed-date');
    if(d.due||d.valid) html+='<br><strong>'+(d.valid?tr('valid'):tr('due'))+':</strong> '+ed('g_due',val('g_due')||'','YYYY-MM-DD','ed-date');
    html+='<br><strong>'+tr('po')+':</strong> '+ed('g_po',val('g_po').trim(),'optional');
    if(d.original&&val('g_original').trim()) html+='<br><strong>Ref invoice:</strong> '+esc(val('g_original').trim());
    if(val('g_doctype')==='credit-note'&&val('g_reason').trim()) html+='<br><strong>Reason:</strong> '+esc(val('g_reason').trim());
    if(val('g_status')) html+='<br><strong>'+tr('status')+':</strong> '+esc(val('g_status'));
    html+='</div></div>';

    const showPrice=!d.noPrice;
    if(!d.noItems){
    // Line items are edited directly in the table, with add and remove inline.
    const liRows=items.querySelectorAll('.line-item');
    html+='<div class="mobile-table"><table class="doc-items"><thead><tr><th>'+tr('description')+'</th><th>'+tr('qty')+'</th>'+(showPrice?('<th>'+tr('price')+'</th><th>'+tr('amount')+'</th>'):'')+'<th class="col-act"></th></tr></thead><tbody>';
    liRows.forEach((rowEl,i)=>{
      const dv2=rowEl.querySelector('.li-desc').value;
      const qv=rowEl.querySelector('.li-qty').value||'1';
      const pv=rowEl.querySelector('.li-price').value;
      const priceMinor=toMinor(pv), q2=parseFloat(qv)||0;
      const grossMinor=M()?M().mulQty(priceMinor,q2):Math.round(priceMinor*q2);
      html+='<tr data-row="'+i+'">'
        + '<td data-label="'+tr('description')+'"><span class="li-lbl">'+tr('description')+'</span>'+edLine(i,'d',dv2,'Description of item or service')+'</td>'
        + '<td data-label="'+tr('qty')+'"><span class="li-lbl">'+tr('qty')+'</span>'+edLine(i,'q',qv,'1','ed-num')+'</td>'
        + (showPrice?('<td data-label="'+tr('price')+'"><span class="li-lbl">'+tr('price')+'</span>'+edLine(i,'p',pv,'0','ed-num')+'</td>'
        + '<td data-label="'+tr('amount')+'" class="li-amount"><span class="li-lbl">'+tr('amount')+'</span><span class="li-amt-v">'+money(grossMinor)+'</span></td>'):'')
        + '<td class="col-act">'+(liRows.length>1?'<button type="button" class="li-del" data-row="'+i+'" aria-label="Remove this line">&#10005;</button>':'')+'</td>'
        + '</tr>';
    });
    html+='</tbody></table></div>';
    html+='<button type="button" class="doc-addline" id="docAddLine">+ Line item</button>';
    }

    if(showPrice&&!d.noItems){
      html+='<div class="totals"><div><span>'+tr('subtotal')+'</span><span id="tSub">'+money(sub)+'</span></div>';
      html+='<div><span>'+ed('g_taxname',taxLabel,'Tax')+' '+ed('g_taxrate',val('g_taxrate'),'0','ed-num ed-rate')+'%</span><span id="tTax">'+money(taxTotal)+'</span></div>';
      html+='<div class="opt-row"><span>'+tr('discount')+' '+ed('g_discval',val('g_discval'),'0','ed-num ed-rate')+'</span><span id="tDisc">-'+money(discount)+'</span></div>';
      html+='<div class="opt-row"><span>'+tr('shipping')+' '+ed('g_shipping',val('g_shipping'),'0','ed-num ed-rate')+'</span><span id="tShip">'+money(shipping)+'</span></div>';
      html+='<div class="grand"><span>'+tr('total')+'</span><span id="tTotal">'+money(total)+'</span></div>';
      html+='<div class="opt-row"><span>'+tr('deposit')+' '+ed('g_deposit',val('g_deposit'),'0','ed-num ed-rate')+'</span><span id="tDep">-'+money(deposit)+'</span></div>';
      if(deposit>0) html+='<div class="grand"><span>'+tr('balance')+'</span><span id="tBal">'+money(balance)+'</span></div>';
      html+='</div>';
    }

    if(d.notice) html+='<p class="doc-notice">'+esc(d.notice)+'</p>';
    if(d.requireTax&&!(val('g_biztax').trim())) html+='<p class="doc-warn">A tax invoice needs your tax registration number. Add it under Your business before sending.</p>';

    const notes=val('g_notes').trim(), bank=val('g_bank').trim(), payvia=val('g_payvia').trim();
    html+='<div class="doc-foot-cols">';
    html+='<div><h3>'+tr('notes')+'</h3><p class="muted">'+edML('g_notes',notes,'Notes, payment terms or a thank-you')+'</p></div>';
    html+='<div><h3>'+tr('payment')+'</h3><p class="muted">'+edML('g_bank',bank,'Account name and transfer instructions')+'</p>'
      + (payvia||true ? ('<p class="muted">Pay via: '+ed('g_payvia',payvia,'PayPal.me link or reference')+'</p>') : '')
      + '</div>';
    html+='</div>';
    if($('g_showsig')&&$('g_showsig').checked) html+='<div class="doc-sign"><span>'+tr('signature')+'</span><span class="sign-line"></span></div>';
    html+='</div>';
    $('invPreview').innerHTML='<div class="doc-scroll">'+html+'</div>';
  }

  // ---- Write edits from the document back into the hidden state ----
  // While someone is typing we must NOT re-render, or the caret jumps to the start.
  // So: on input we update the underlying field and refresh only the numbers.
  // On blur we do a full render, which re-applies formatting and any layout changes.
  const preview=$('invPreview');
  let editing=false;
  let saveTimer=null;      // hoisted: used by the inline-edit handler below
  let lastRemoved=null;    // hoisted: used by inline line removal

  function recalcNumbersOnly(){
    const d=currentDoc(); const rows=lineData();
    let sub=0,taxTotal=0; rows.forEach(r=>{sub+=r.netMinor;taxTotal+=r.taxMinor;});
    const dv=val('g_discval'),dt=val('g_disctype');
    const discount=(dt==='fixed'?toMinor(dv):(M()?M().pct(sub,dv):0));
    const shipping=toMinor(val('g_shipping'));
    const deposit=toMinor(val('g_deposit'));
    const total=Math.max(0,sub-discount)+taxTotal+shipping;
    const balance=Math.max(0,total-deposit);
    const put=(id,v)=>{const el=$(id); if(el) el.textContent=v;};
    put('tSub',money(sub)); put('tTax',money(taxTotal)); put('tDisc','-'+money(discount));
    put('tShip',money(shipping)); put('tTotal',money(total)); put('tDep','-'+money(deposit));
    put('tBal',money(balance));
    // per-row amounts
    preview.querySelectorAll('tr[data-row]').forEach(tr2=>{
      const i=parseInt(tr2.dataset.row,10); const r=rows[i]; const cell=tr2.querySelector('.li-amount');
      if(cell&&r) cell.textContent=money(r.grossMinor);
    });
  }

  if(preview){
    // The overlaid date input writes straight back to its bound field.
    // On desktop the segments are typeable, so keep the printed text in step as
    // they type. A full render here would destroy the input mid-entry and drop
    // focus, so only the visible text and the bound field are touched.
    // While a picker is open, nothing may re-render the document.
    preview.addEventListener('focusin',e=>{
      if(e.target && e.target.classList && e.target.classList.contains('ed-date-input')) editing=true;
    });
    preview.addEventListener('input',e=>{
      const inp=e.target.closest && e.target.closest('.ed-date-input'); if(!inp) return;
      editing=true;
      const f=$(inp.dataset.bind); if(f) f.value=inp.value;
      const wrap=inp.closest('.ed-date-wrap');
      const txt=wrap && wrap.querySelector('.ed-date-text');
      if(txt) txt.textContent=inp.value||(inp.getAttribute('data-ph')||'');
    },true);
    preview.addEventListener('change',e=>{
      const inp=e.target.closest && e.target.closest('.ed-date-input'); if(!inp) return;
      const f=$(inp.dataset.bind); if(!f) return;
      f.value=inp.value;
      editing=false;
      render();
    });
    preview.addEventListener('input',e=>{
      // The date overlay is a real input inside a .ed wrapper; its events must
      // not be treated as someone typing into the document.
      if(e.target && e.target.classList && e.target.classList.contains('ed-date-input')) return;
      const el=e.target.closest('.ed'); if(!el) return;
      editing=true;
      const text=edText(el);
      if(el.dataset.bind){
        const f=$(el.dataset.bind); if(f){ f.value=text; }
      } else if(el.dataset.line!=null){
        const rowEl=items.querySelectorAll('.line-item')[parseInt(el.dataset.line,10)];
        if(rowEl){
          const key=el.dataset.key;
          const sel=key==='d'?'.li-desc':(key==='q'?'.li-qty':'.li-price');
          const input=rowEl.querySelector(sel); if(input) input.value=text;
        }
      }
      recalcNumbersOnly();
      clearTimeout(saveTimer); saveTimer=setTimeout(saveDraft,500);
    });
    // Full re-render once they leave the document entirely, so formatting settles.
    // Critical: if focus is simply moving to ANOTHER editable field, we must not
    // re-render, because that destroys the element the person just clicked into and
    // their next keystrokes go nowhere.
    preview.addEventListener('focusout',e=>{
      // The date overlay commits on change and renders itself; this generic
      // path would fire a second render 120ms later and fight it.
      if(e.target && e.target.classList && e.target.classList.contains('ed-date-input')) return;
      if(!e.target.closest || !e.target.closest('.ed')) return;
      const goingTo=e.relatedTarget;
      if(goingTo && preview.contains(goingTo) && goingTo.closest && goingTo.closest('.ed')){
        return; // moving between fields inside the document: keep the DOM intact
      }
      editing=false;
      setTimeout(()=>{
        // Re-check: focus may have landed on another editable in the meantime.
        const a=document.activeElement;
        if(a && preview.contains(a) && a.closest && a.closest('.ed')) return;
        render();
      },120);
    });
    // Numeric fields select their contents on focus, so typing replaces the existing
    // value instead of appending to it. Without this, a field showing 0 turns into
    // "200" when someone types 20, which silently produces wrong totals.
    preview.addEventListener('focusin',e=>{
      const el=e.target.closest && e.target.closest('.ed'); if(!el) return;
      if(!el.classList.contains('ed-num') && !el.classList.contains('ed-rate')) return;
      // Only auto-select when the field is showing a zero/empty default. Never wipe a
      // real value the person has already entered.
      const txt=(el.textContent||'').trim();
      if(txt==='' || parseFloat(txt)===0){
        // Clear the placeholder zero outright and sync the hidden field, rather than
        // relying on the selection being replaced. Selecting alone left the underlying
        // value at 0, so typing 20 produced 200 and silently tripled the tax.
        el.textContent='';
        if(el.dataset.bind){ const f=$(el.dataset.bind); if(f) f.value=''; }
        else if(el.dataset.line!=null){
          const rowEl=items.querySelectorAll('.line-item')[parseInt(el.dataset.line,10)];
          if(rowEl){ const k=el.dataset.key; const q=rowEl.querySelector(k==='d'?'.li-desc':(k==='q'?'.li-qty':'.li-price')); if(q) q.value=''; }
        }
      }
    });
    // Enter should not insert raw HTML line breaks in single-line fields.
    preview.addEventListener('keydown',e=>{
      const el=e.target.closest && e.target.closest('.ed'); if(!el) return;
      if(e.key==='Enter' && !el.classList.contains('ed-ml')){ e.preventDefault(); el.blur(); }
    });
    // Paste as plain text so pasted formatting never breaks the document.
    preview.addEventListener('paste',e=>{
      const el=e.target.closest && e.target.closest('.ed'); if(!el) return;
      e.preventDefault();
      const t=(e.clipboardData||window.clipboardData).getData('text/plain');
      document.execCommand('insertText',false,t);
    });
    // Inline add and remove for line items.
    preview.addEventListener('click',e=>{
      const add=e.target.closest('#docAddLine');
      if(add){ const b=$('genAddLine'); if(b){ b.click(); } else { const nd=document.createElement('div'); nd.className='line-item'; nd.innerHTML=rowInner; items.appendChild(nd); render(); } return; }
      const del=e.target.closest('.li-del');
      if(del){
        const i=parseInt(del.dataset.row,10);
        const rowsEls=items.querySelectorAll('.line-item');
        if(rowsEls.length>1 && rowsEls[i]){
          lastRemoved={node:rowsEls[i],index:i};
          rowsEls[i].remove(); render(); showUndo(); announce('Line removed.');
        }
      }
    });
  }

  // The document preview lives INSIDE root, so events from the date overlay
  // bubble up here. Without this guard, a native picker firing input (iOS fires
  // it on every scroll of the wheel) re-renders the document, destroys the very
  // input being used, and closes the picker before the value can commit. That
  // is why the issue date appeared stuck on today and the due date never moved.
  const fromDateOverlay = e => !!(e && e.target && e.target.classList
    && e.target.classList.contains('ed-date-input'));

  root.addEventListener('input',e=>{ if(fromDateOverlay(e)) return; if(!editing) render(); });
  // Form date inputs (the Details card) still need change, since a picker does
  // not always fire input there. The overlay handles its own commit.
  root.addEventListener('change',e=>{
    if(fromDateOverlay(e)) return;
    if(e.target && e.target.type==='date'){ editing=false; render(); }
  });

  // Due-date presets. Offsets run from the ISSUE date, not from today, so
  // back-dating an invoice still yields correct terms.
  (function(){
    const presets=root.querySelector('.due-presets'); if(!presets) return;
    presets.addEventListener('click',e=>{
      const chip=e.target.closest('.due-chip'); if(!chip) return;
      const issueEl=$('g_issue'), dueEl=$('g_due'); if(!issueEl||!dueEl) return;
      const base=issueEl.value?new Date(issueEl.value+'T00:00:00'):new Date();
      if(isNaN(base.getTime())) return;
      const days=parseInt(chip.dataset.days,10)||0;
      base.setDate(base.getDate()+days);
      const pad=n=>String(n).padStart(2,'0');
      dueEl.value=base.getFullYear()+'-'+pad(base.getMonth()+1)+'-'+pad(base.getDate());
      presets.querySelectorAll('.due-chip').forEach(c=>c.classList.toggle('is-active',c===chip));
      editing=false; render();
    });
    // A manual edit clears the preset highlight; it no longer describes the value.
    const dueEl=$('g_due');
    if(dueEl) dueEl.addEventListener('input',()=>{
      presets.querySelectorAll('.due-chip').forEach(c=>c.classList.remove('is-active'));
    });
  })();
  root.addEventListener('change',e=>{ if(fromDateOverlay(e)) return; if(!editing) render(); });

  // Template gallery
  const tmplBtns=root.querySelectorAll('[data-template]');
  tmplBtns.forEach(b=>b.addEventListener('click',()=>{root.dataset.template=b.dataset.template;tmplBtns.forEach(x=>x.classList.toggle('is-active',x===b));render();}));

  // ---- Access gate, ported from the Card Maker Messages flow ----
  // Free uses are counted on this browser. Past the limit, the person verifies an email
  // and the SERVER sets a session cookie which unlocks unlimited use here.
  const usesKey='ign_uses';
  const getUses=()=>{try{return parseInt(localStorage.getItem(usesKey)||'0',10)||0;}catch(e){return 0;}};
  const setUses=n=>{try{localStorage.setItem(usesKey,String(n));}catch(e){}};
  const label=$('genUses');
  let verifiedAccess=false;

  function updateLabel(){
    if(!label) return;
    if(verifiedAccess){ label.textContent='Verified. Unlimited downloads on this browser.'; return; }
    const left=Math.max(0,free-getUses());
    label.textContent=left>0?(left+' download'+(left===1?'':'s')+' left on this browser'):'Verify your email to keep going';
  }

  async function readVerifiedAccess(showSuccess){
    try{
      const r=await fetch('/api/status.php',{headers:{Accept:'application/json'},credentials:'same-origin',cache:'no-store'});
      const p=await r.json().catch(()=>({}));
      verifiedAccess=!!(r.ok&&p.verified);
      if(verifiedAccess&&showSuccess) announce('Email verified. Unlimited use is now unlocked on this browser.');
    }catch(e){ verifiedAccess=false; }
    updateLabel();
    return verifiedAccess;
  }

  const modal=$('gateModal');
  function showGate(){ if(!modal)return; modal.hidden=false; modal.classList.add('is-open'); document.body.style.overflow='hidden'; }
  function hideGate(){ if(!modal)return; modal.hidden=true; modal.classList.remove('is-open'); document.body.style.overflow=''; }
  if($('gateClose')) $('gateClose').addEventListener('click',hideGate);
  if(modal) modal.addEventListener('click',e=>{if(e.target===modal)hideGate();});

  // Signup form handling now lives in assets/signup.js so it works on every page.

  // On load: ask the server whether this browser is verified, and clear the ?verified=1 flag.
  (function(){
    const params=new URLSearchParams(location.search);
    const returned=params.get('verified')==='1';
    readVerifiedAccess(returned).then(ok=>{
      if(returned){
        params.delete('verified');
        const q=params.toString();
        history.replaceState(null,'',location.pathname+(q?('?'+q):'')+location.hash);
        if(!ok) showGate();
      }
    });
  })();

  $('genReset').addEventListener('click',()=>{root.querySelectorAll('input,textarea').forEach(i=>{if(i.type==='file')i.value='';else if(i.type==='checkbox')i.checked=(i.id==='g_sameaddr');else if(i.id==='g_number')i.value=currentDoc().prefix+'0001';else if(i.classList.contains('li-qty'))i.value='1';else if(i.id==='g_taxname')i.value='Tax';else if(i.id==='g_taxrate'||i.id==='g_discval'||i.id==='g_shipping'||i.id==='g_deposit')i.value='0';else i.value='';});logoData='';$('g_issue').value=iso(today);$('g_due').value=iso(due);render();});

  // ---- Auto-save everything to the browser so nothing is lost on refresh ----
  const saveKey='ign_draft_v1';
  const fieldIds=['g_doctype','g_doclang','g_bizname','g_bizemail','g_bizphone','g_bizaddr','g_biztax','g_country','g_currency','g_clientname','g_clientemail','g_clientaddr','g_deliveraddr','g_number','g_issue','g_due','g_po','g_terms','g_original','g_reason','g_taxname','g_taxrate','g_discval','g_disctype','g_shipping','g_deposit','g_status','g_notes','g_bank','g_payvia'];
  function collect(){
    const o={fields:{},checks:{},lines:[],mode:'adv',template:root.dataset.template||'modern',logo:logoData||''};
    fieldIds.forEach(id=>{const el=$(id);if(el)o.fields[id]=el.value;});
    ['g_sameaddr','g_taxincl','g_showsig'].forEach(id=>{const el=$(id);if(el)o.checks[id]=el.checked;});
    items.querySelectorAll('.line-item').forEach(r=>{o.lines.push({d:r.querySelector('.li-desc').value,q:r.querySelector('.li-qty').value,p:r.querySelector('.li-price').value,t:(r.querySelector('.li-tax')||{}).value||''});});
    return o;
  }
  function saveDraft(){try{localStorage.setItem(saveKey,JSON.stringify(collect()));}catch(e){}}
  function restoreDraft(){
    let o=null;try{o=JSON.parse(localStorage.getItem(saveKey)||'null');}catch(e){}
    if(!o||!o.fields) return false;
    // rebuild line rows to match
    if(o.lines&&o.lines.length){items.innerHTML='';o.lines.forEach(()=>{const d=document.createElement('div');d.className='line-item';d.innerHTML=rowInner;items.appendChild(d);});
      const rows=items.querySelectorAll('.line-item');o.lines.forEach((ln,i)=>{const r=rows[i];if(!r)return;r.querySelector('.li-desc').value=ln.d||'';r.querySelector('.li-qty').value=ln.q||'1';r.querySelector('.li-price').value=ln.p||'';const lt=r.querySelector('.li-tax');if(lt)lt.value=ln.t||'';});}
    fieldIds.forEach(id=>{const el=$(id);if(el&&o.fields[id]!=null)el.value=o.fields[id];});
    Object.keys(o.checks||{}).forEach(id=>{const el=$(id);if(el)el.checked=!!o.checks[id];});
    if(o.template){root.dataset.template=o.template;tmplBtns.forEach(x=>x.classList.toggle('is-active',x.dataset.template===o.template));}
    if(o.logo){logoData=o.logo;}
    if(o.fields.g_currency&&currencies[o.fields.g_currency]){const c=currencies[o.fields.g_currency];CUR={s:c.s,p:c.p,d:c.d};}
    setMode();
    return true;
  }
  // The draft is restored silently. No banner: it misled first-time visitors into
  // thinking they had an account, and it implied we store their data.
  restoreDraft();
  root.addEventListener('input',e=>{ if(fromDateOverlay(e)) return; clearTimeout(saveTimer);saveTimer=setTimeout(saveDraft,400);});
  window.addEventListener('pagehide',saveDraft);

  // ---- Recently used documents, held locally, no account required ----
  // A short list of past documents so someone can start the next one from an old one
  // without retyping everything. Nothing here ever leaves the browser.
  const historyKey='ign_history_v1';
  const HISTORY_MAX=5;
  function loadHistory(){ try{ const h=JSON.parse(localStorage.getItem(historyKey)||'[]'); return Array.isArray(h)?h:[]; }catch(e){ return []; } }
  function saveHistory(list){ try{ localStorage.setItem(historyKey, JSON.stringify(list.slice(0,HISTORY_MAX))); }catch(e){} }
  function historyLabel(o){
    const doc=(DOCS[o.fields.g_doctype||'invoice']||DOCS['invoice']||{}).title||'Document';
    const who=(o.fields.g_clientname||'').trim();
    const num=(o.fields.g_number||'').trim();
    return doc+(who?(' for '+who):'')+(num?(' ('+num+')'):'');
  }
  // Snapshot the current document into history. Called on download, so history only
  // fills with things actually produced, not every abandoned draft.
  function pushHistory(){
    try{
      const snap=collect();
      const list=loadHistory().filter(h=>historyLabel(h)!==historyLabel(snap)); // no immediate duplicates
      list.unshift({ ...snap, savedAt: Date.now() });
      saveHistory(list);
      renderHistory();
    }catch(e){}
  }
  function applyHistoryEntry(o){
    if(o.lines&&o.lines.length){items.innerHTML='';o.lines.forEach(()=>{const d=document.createElement('div');d.className='line-item';d.innerHTML=rowInner;items.appendChild(d);});
      const rows=items.querySelectorAll('.line-item');o.lines.forEach((ln,i)=>{const r=rows[i];if(!r)return;r.querySelector('.li-desc').value=ln.d||'';r.querySelector('.li-qty').value=ln.q||'1';r.querySelector('.li-price').value=ln.p||'';const lt=r.querySelector('.li-tax');if(lt)lt.value=ln.t||'';});}
    fieldIds.forEach(id=>{const el=$(id);if(el&&o.fields[id]!=null)el.value=o.fields[id];});
    Object.keys(o.checks||{}).forEach(id=>{const el=$(id);if(el)el.checked=!!o.checks[id];});
    if(o.template){root.dataset.template=o.template;tmplBtns.forEach(x=>x.classList.toggle('is-active',x.dataset.template===o.template));}
    if(o.logo){logoData=o.logo;refreshLogoUI();}
    if(o.fields.g_currency&&currencies[o.fields.g_currency]){const c=currencies[o.fields.g_currency];CUR={s:c.s,p:c.p,d:c.d};}
    setMode();render();if(window.__ignSyncOptions)window.__ignSyncOptions();try{saveDraft();}catch(e){}
  }
  function renderHistory(){
    const wrap=$('historyList'); const panel=$('historyPanel');
    if(!wrap||!panel) return;
    const list=loadHistory();
    if(!list.length){ panel.hidden=true; return; }
    panel.hidden=false;
    wrap.innerHTML=list.map((o,i)=>{
      const label=historyLabel(o);
      const total=(()=>{try{
        let sub=0;(o.lines||[]).forEach(l=>{const p=toMinor(l.p||'0');const q=parseFloat(l.q)||0;sub+=M()?M().mulQty(p,q):Math.round(p*q);});
        const cur=currencies[o.fields.g_currency]||{s:'',p:'before',d:2};
        return M()?M().format(sub,cur.s,cur.p,cur.d):'';
      }catch(e){return '';}})();
      return '<button type="button" class="history-item" data-i="'+i+'"><span class="history-label">'+escapeHtml(label)+'</span><span class="history-meta">'+escapeHtml(total)+'</span></button>';
    }).join('');
    wrap.querySelectorAll('.history-item').forEach(btn=>{
      btn.addEventListener('click',()=>{
        const i=parseInt(btn.dataset.i,10); const entry=loadHistory()[i];
        if(entry) applyHistoryEntry(entry);
      });
    });
  }
  function escapeHtml(s){ return String(s||'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
  renderHistory();

  // ---- Duplicate: start the next document from this one, without retyping ----
  { const dup=$('genDuplicate'); if(dup) dup.addEventListener('click',()=>{
      const snap=collect();
      // A duplicate should feel like "the next one", not an exact clone: give it a
      // fresh document number and today's dates, keep everything else.
      snap.fields=Object.assign({},snap.fields);
      const d=currentDoc();
      snap.fields.g_number = d.prefix ? (d.prefix+'0001') : '';
      snap.fields.g_issue = iso(today);
      snap.fields.g_due = iso(due);
      applyHistoryEntry(snap);
      announce('Duplicated. Update the details and download when ready.');
    }); }

  // ---- Share with attribution backlink (distribution loop) ----
  const shareBtn=$('genShare'), shareMenu=$('shareMenu');
  function shareUrl(){
    const slug=(currentDoc().title||'invoice').toLowerCase().replace(/[^a-z]+/g,'-').replace(/^-|-$/g,'');
    // Link back to the matching template page (or home) so recipients land on a real page.
    const map={'invoice':'/invoice-template.php','quote':'/quote-template.php','estimate':'/estimate-template.php','receipt':'/receipt-template.php','purchase order':'/purchase-order-template.php','credit note':'/credit-note-template.php'};
    const base=location.origin||'https://invoicegeneratornow.com';
    return base+'/';
  }
  function shareText(){
    const biz=val('g_bizname').trim()||'a business';
    const docName=(currentDoc().title||'INVOICE').toLowerCase();
    return 'Here is your '+docName+' from '+biz+'. This '+docName+' was made free at InvoiceGeneratorNow. You can make yours too, free: '+shareUrl();
  }
  function refreshShareLinks(){
    if(!shareMenu) return;
    const t=encodeURIComponent(shareText());
    const wa=$('shareWhatsapp'); if(wa) wa.href='https://wa.me/?text='+t;
    const em=$('shareEmail'); if(em) em.href='mailto:?subject='+encodeURIComponent((currentDoc().title||'Invoice')+' from '+(val('g_bizname').trim()||'us'))+'&body='+t;
  }
  function openShare(){shareMenu.hidden=false;shareMenu.classList.add('is-open');}
  function closeShare(){shareMenu.hidden=true;shareMenu.classList.remove('is-open');}
  if(shareBtn&&shareMenu){
    closeShare();
    shareBtn.addEventListener('click',e=>{
      e.preventDefault();e.stopPropagation();
      refreshShareLinks();
      if(shareMenu.classList.contains('is-open')) closeShare(); else openShare();
    });
    const copyBtn=$('shareCopy');
    if(copyBtn) copyBtn.addEventListener('click',()=>{
      const text=shareText();
      const done=()=>{copyBtn.textContent='Copied';setTimeout(()=>copyBtn.textContent='Copy link',1500);};
      if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(text).then(done).catch(done);}
      else{try{const ta=document.createElement('textarea');ta.value=text;document.body.appendChild(ta);ta.select();document.execCommand('copy');document.body.removeChild(ta);}catch(e){}done();}
    });
    document.addEventListener('click',e=>{
      if(shareMenu.classList.contains('is-open')&&!shareMenu.contains(e.target)&&e.target!==shareBtn) closeShare();
    });
  }

  // Full-screen document preview (mobile): the panel stays compact, this shows it properly.
  const fsBtn=$('previewExpand'), fsClose=$('previewClose');
  function openFs(){document.body.classList.add('doc-fs');if(fsBtn)fsBtn.setAttribute('aria-expanded','true');}
  function closeFs(){document.body.classList.remove('doc-fs');if(fsBtn)fsBtn.setAttribute('aria-expanded','false');}
  if(fsBtn) fsBtn.addEventListener('click',()=>{document.body.classList.contains('doc-fs')?closeFs():openFs();});
  if(fsClose) fsClose.addEventListener('click',closeFs);
  document.addEventListener('keydown',e=>{if(e.key==='Escape')closeFs();});


  // ---- Data safety net: export / import a backup file ----
  // Browser storage can be cleared by the person, by private mode, or by iOS under
  // storage pressure. A downloadable backup means work is never trapped in one browser.
  function exportBackup(){
    try{
      const payload={format:'invoicegeneratornow-backup',version:1,savedAt:new Date().toISOString(),draft:collect()};
      const blob=new Blob([JSON.stringify(payload,null,2)],{type:'application/json'});
      const a=document.createElement('a');
      a.href=URL.createObjectURL(blob);
      a.download='invoicegeneratornow-backup-'+new Date().toISOString().slice(0,10)+'.json';
      document.body.appendChild(a);a.click();
      setTimeout(()=>{URL.revokeObjectURL(a.href);a.remove();},0);
      announce('Backup downloaded.');
    }catch(e){alert('Could not create the backup file.');}
  }
  function importBackup(file){
    const r=new FileReader();
    r.onload=()=>{
      try{
        const data=JSON.parse(r.result);
        const draft=(data&&data.draft)?data.draft:data;
        if(!draft||!draft.fields) throw new Error('bad');
        localStorage.setItem(saveKey,JSON.stringify(draft));
        announce('Backup restored.');
        location.reload();
      }catch(e){alert('That file is not a valid backup.');}
    };
    r.readAsText(file);
  }

  // ---- Screen-reader announcements ----
  function announce(msg){
    const live=document.querySelector('.sr-live');
    if(live){ live.textContent=''; setTimeout(()=>{live.textContent=msg;},50); }
  }

  // ---- Undo for a removed line ----
  function showUndo(){
    let bar=$('undoBar');
    if(!bar){
      bar=document.createElement('div');bar.id='undoBar';bar.className='undo-bar';
      bar.innerHTML='<span>Line removed.</span><button type="button" class="link-btn" id="undoBtn">Undo</button>';
      document.body.appendChild(bar);
      bar.querySelector('#undoBtn').addEventListener('click',()=>{
        if(lastRemoved){ items.insertBefore(lastRemoved.node,items.children[lastRemoved.index]||null); lastRemoved=null; render(); announce('Line restored.'); }
        bar.classList.remove('is-open');
      });
    }
    bar.classList.add('is-open');
    clearTimeout(showUndo._t);
    showUndo._t=setTimeout(()=>bar.classList.remove('is-open'),6000);
  }

  // ---- Keyboard shortcuts ----
  document.addEventListener('keydown',e=>{
    const typing=/^(INPUT|TEXTAREA|SELECT)$/.test((e.target&&e.target.tagName)||'');
    if((e.ctrlKey||e.metaKey)&&e.key==='Enter'){ e.preventDefault(); const b=$('genAddLine'); if(b){b.click();announce('Line added.');} return; }
    if((e.ctrlKey||e.metaKey)&&(e.key==='s'||e.key==='S')){ e.preventDefault(); const b=$('genDownload'); if(b)b.click(); return; }
  });

  // ---- Image downloads (PNG / JPG) ----
  function baseName(ext){ return fileName().replace(/\.pdf$/,'.'+ext); }
  // Raster exports must target the document element, not the scroll wrapper around it,
  // or the image is clipped to the visible scroll area on mobile.
  function docEl(){ return $('invPreview').querySelector('.doc') || $('invPreview'); }
  function gateOrRun(fn){
    const d=currentDoc();
    if(d.requireTax&&!(val('g_biztax').trim())){alert('A tax invoice needs your tax registration number. Add it under Your business first.');const tx=$('g_biztax');if(tx)tx.focus();return;}
    if(!verifiedAccess && getUses()>=free){showGate();return;}
    try{saveDraft();}catch(e){}
    render();
    fn();
    setUses(getUses()+1);updateLabel();
  }
  // ---- Build the structured payload the vector PDF engine draws from ----
  function pdfPayload(){
    const d=currentDoc();
    const rows=lineData();
    let sub=0,taxTotal=0; rows.forEach(r=>{sub+=r.netMinor;taxTotal+=r.taxMinor;});
    const dv=val('g_discval'),dt=val('g_disctype');
    const discount=(dt==='fixed'?toMinor(dv):(M()?M().pct(sub,dv):0));
    const shipping=toMinor(val('g_shipping'));
    const deposit=toMinor(val('g_deposit'));
    const total=Math.max(0,sub-discount)+taxTotal+shipping;
    const balance=Math.max(0,total-deposit);
    const taxLabel=val('g_taxname').trim()||tr('tax');
    const showPrice=!d.noPrice, noItems=!!d.noItems;

    const accentMap={modern:[16,36,73],clean:[31,111,235],minimal:[17,17,17],corporate:[11,61,46],
      blue:[19,80,168],elegant:[107,74,138],creative:[194,65,12],construction:[180,83,9],
      cleaning:[8,145,178],consulting:[51,65,85],healthcare:[14,116,144],mono:[0,0,0]};
    const accent=accentMap[root.dataset.template||'modern']||accentMap.modern;

    const contact=[val('g_bizemail').trim(),val('g_bizphone').trim()]
      .concat(val('g_bizaddr').trim().split('\n'))
      .concat(val('g_biztax').trim()?['Tax reg: '+val('g_biztax').trim()]:[])
      .filter(Boolean);
    const to=[val('g_clientname').trim(),val('g_clientemail').trim()]
      .concat(val('g_clientaddr').trim().split('\n')).filter(Boolean);
    const deliverTo=($('g_sameaddr')&&!$('g_sameaddr').checked)?val('g_deliveraddr').trim().split('\n').filter(Boolean):[];

    const meta=[];
    meta.push([tr('issue'),val('g_issue')||'-']);
    if(d.due||d.valid) meta.push([d.valid?tr('valid'):tr('due'),val('g_due')||'-']);
    if(val('g_po').trim()) meta.push([tr('po'),val('g_po').trim()]);
    if(d.original&&val('g_original').trim()) meta.push(['Ref invoice',val('g_original').trim()]);
    if(val('g_doctype')==='credit-note'&&val('g_reason').trim()) meta.push(['Reason',val('g_reason').trim()]);
    if(val('g_status')) meta.push([tr('status'),val('g_status')]);
    if(val('g_terms')) meta.push(['Terms',val('g_terms')]);

    const columns = noItems ? [] : (showPrice
      ? [{label:tr('description'),width:88,bold:true},{label:tr('qty'),width:20,align:'right'},
         {label:tr('price'),width:35,align:'right'},{label:tr('amount'),width:35,align:'right'}]
      : [{label:tr('description'),width:138,bold:true},{label:tr('qty'),width:40,align:'right'}]);

    const pdfRows = noItems ? [] : rows.map(r=>({cells: showPrice
      ? [r.desc,String(r.q),money(r.priceMinor),money(r.grossMinor)]
      : [r.desc,String(r.q)]}));

    const totals=[];
    if(showPrice&&!noItems){
      totals.push([tr('subtotal'),money(sub),false]);
      if(discount>0) totals.push([tr('discount'),'-'+money(discount),false]);
      if(taxTotal>0) totals.push([taxLabel,money(taxTotal),false]);
      if(shipping>0) totals.push([tr('shipping'),money(shipping),false]);
      totals.push([tr('total'),money(total),true]);
      if(deposit>0){ totals.push([tr('deposit'),'-'+money(deposit),false]);
                     totals.push([tr('balance'),money(balance),true]); }
    }

    const notes=[];
    if(val('g_notes').trim()) notes.push([tr('notes'),val('g_notes').trim()]);
    if(val('g_bank').trim()) notes.push([tr('payment'),val('g_bank').trim()]);
    if(val('g_payvia').trim()) notes.push(['Pay via',val('g_payvia').trim()]);

    return {
      title:d.title, number:val('g_number')||d.prefix+'0001',
      brand:val('g_bizname').trim()||'Your business',
      contact:contact, toLabel:d.to, to:to, deliverTo:deliverTo, meta:meta,
      columns:columns, rows:pdfRows, totals:totals, notes:notes,
      accent:accent, logo:logoData||null,
      showSignature:!!($('g_showsig')&&$('g_showsig').checked),
      signatureLabel:tr('signature'),
      notice:d.notice||'',
      footerNote:(val('g_bizname').trim()||'')
    };
  }

  function fileName(){
    const d=currentDoc();
    const slug=(d.title||'document').toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'');
    const num=(val('g_number')||'').replace(/[^A-Za-z0-9\-_]/g,'');
    return ['invoicegeneratornow',slug,num].filter(Boolean).join('-')+'.pdf';
  }


  // Main PDF download. This handler was lost in an earlier edit, which is why the
  // button stopped responding; restored here alongside the PNG and JPG handlers.
  { const dl=$('genDownload'); if(dl) dl.addEventListener('click',()=>gateOrRun(()=>{
      if(window.IGN_Export && window.IGN_PDF && window.IGN_PDF.available()){
        window.IGN_Export.pdf(pdfPayload(), fileName());
      } else {
        setTimeout(()=>{try{window.print();}catch(e){}},50);
      }
      pushHistory();
    })); }

  { const pn=$('genDownloadPng'); if(pn) pn.addEventListener('click',()=>gateOrRun(()=>{
      announce('Building the image.');
      window.IGN_Export.png(docEl(), baseName('png')).catch(()=>alert('Could not create the image on this browser.'));
    }));
    const jp=$('genDownloadJpg'); if(jp) jp.addEventListener('click',()=>gateOrRun(()=>{
      announce('Building the image.');
      window.IGN_Export.jpg(docEl(), baseName('jpg')).catch(()=>alert('Could not create the image on this browser.'));
    })); }

  // ---- Share the real FILE (WhatsApp, Mail, Messages all receive it) ----
  function shareAs(format){
    gateOrRun(()=>{
      const btnText='Preparing…';
      announce('Preparing the document to share.');
      window.IGN_Export.shareFile({
        format:format,
        payload:pdfPayload(),
        element:docEl(),
        filename:baseName(format),
        title:(currentDoc().title||'Document')+' '+(val('g_number')||''),
        text:shareText()
      }).then(r=>{
        if(r==='downloaded') announce('Your browser cannot attach files directly, so the file was downloaded for you to attach.');
        if(r==='shared') announce('Shared.');
      }).catch(()=>alert('Could not prepare the document on this browser.'));
    });
  }
  { const a=$('shareDocPdf'); if(a) a.addEventListener('click',()=>shareAs('pdf'));
    const b2=$('shareDocPng'); if(b2) b2.addEventListener('click',()=>shareAs('png'));
    const c=$('shareDocJpg'); if(c) c.addEventListener('click',()=>shareAs('jpg'));
    // Tell the person honestly what their device can do
    const note=$('shareNote');
    if(note&&window.IGN_Export&&!window.IGN_Export.canShareFiles([new Blob(['x'],{type:'text/plain'})])){
      note.textContent='This browser cannot attach files to a share, so the file will download and you can attach it yourself.';
    } }


  // ---- Options panel proxies ----
  // The document is the editing surface, but a few controls (currency, logo, toggles)
  // have no sensible place on a printed document. They live in a compact panel and
  // mirror to the original inputs, which remain the single state store.
  (function(){
    const pairs=[['o_currency','g_currency'],['o_country','g_country'],['o_terms','g_terms'],
                 ['o_status','g_status'],['o_disctype','g_disctype'],['o_doclang','g_doclang']];
    const checks=[['o_taxincl','g_taxincl'],['o_sameaddr','g_sameaddr'],['o_showsig','g_showsig']];
    // Clone option lists that are populated dynamically (currency, country, language).
    function mirrorOptions(fromId,toId){
      const src=$(fromId), dst=$(toId); if(!src||!dst) return;
      dst.innerHTML=src.innerHTML; dst.value=src.value;
    }
    function syncDown(){ // real -> proxy
      pairs.forEach(([o,g])=>{const a=$(o),b=$(g); if(a&&b){ if(a.options&&a.options.length!==b.options.length) mirrorOptions(g,o); a.value=b.value; }});
      checks.forEach(([o,g])=>{const a=$(o),b=$(g); if(a&&b) a.checked=b.checked;});
    }
    pairs.forEach(([o,g])=>{
      const a=$(o),b=$(g); if(!a||!b) return;
      mirrorOptions(g,o);
      a.addEventListener('change',()=>{ b.value=a.value; b.dispatchEvent(new Event('change',{bubbles:true})); });
    });
    checks.forEach(([o,g])=>{
      const a=$(o),b=$(g); if(!a||!b) return;
      a.checked=b.checked;
      a.addEventListener('change',()=>{ b.checked=a.checked; b.dispatchEvent(new Event('change',{bubbles:true})); });
    });
    // Logo proxy
    const ol=$('o_logo'), gl=$('g_logo');
    if(ol&&gl) ol.addEventListener('change',e=>{
      const f=e.target.files&&e.target.files[0]; if(!f) return;
      const dt=new DataTransfer(); dt.items.add(f); gl.files=dt.files;
      gl.dispatchEvent(new Event('change',{bubbles:true}));
    });
    const orm=$('o_logoRemove'); if(orm) orm.addEventListener('click',()=>{ const r=$('logoRemove'); if(r) r.click(); });
    // Keep the proxy logo thumbnail in step with the real one
    const realThumb=$('logoThumb'), proxThumb=$('o_logoThumb'), proxRm=$('o_logoRemove');
    function syncLogo(){
      if(!proxThumb||!realThumb) return;
      if(logoData){ proxThumb.src=logoData; proxThumb.hidden=false; if(proxRm) proxRm.hidden=false; }
      else { proxThumb.removeAttribute('src'); proxThumb.hidden=true; if(proxRm) proxRm.hidden=true; }
    }
    window.__ignSyncOptions=function(){ syncDown(); syncLogo(); };
    syncDown(); syncLogo();
  })();

  // init
  setMode();
  applyDoc();
  render();
})();
