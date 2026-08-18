/* InvoiceGeneratorNow — real vector PDF output.
   Replaces browser print-to-PDF so the file is identical on every browser and device.
   Handles multi-page documents: repeated column headers, controlled break points,
   "Page X of Y" on every page, and totals kept together with the last rows. */
(function(){
  'use strict';

  // ---- Money as integer minor units (no floating point on currency) ----
  // Every amount is held in minor units (pence/cents). Rates are applied with
  // integer maths and half-up rounding, so totals cannot drift by a penny.
  const Money = {
    // "12.34" -> 1234 (minor units), respecting the currency's decimal places
    parse(v, decimals){
      const d = (decimals==null?2:decimals);
      const n = String(v==null?'':v).replace(/[^0-9.\-]/g,'');
      if(n===''||n==='-'||n==='.') return 0;
      const f = parseFloat(n);
      if(!isFinite(f)) return 0;
      return Math.round(f * Math.pow(10,d));
    },
    // multiply a minor-unit amount by a plain quantity, rounding half-up
    mulQty(minor, qty){
      const q = parseFloat(qty);
      if(!isFinite(q)) return 0;
      return Math.round(minor * q);
    },
    // apply a percentage rate (e.g. 20 for 20%) with half-up rounding
    pct(minor, rate){
      const r = parseFloat(rate);
      if(!isFinite(r)||r===0) return 0;
      return Math.round(minor * r / 100);
    },
    // extract the tax already contained in a gross amount (tax-inclusive pricing)
    inclusiveTax(grossMinor, rate){
      const r = parseFloat(rate);
      if(!isFinite(r)||r===0) return 0;
      const net = Math.round(grossMinor * 100 / (100 + r));
      return grossMinor - net;
    },
    format(minor, sym, pos, decimals){
      const d = (decimals==null?2:decimals);
      const neg = minor < 0;
      const abs = Math.abs(minor);
      const whole = Math.floor(abs / Math.pow(10,d));
      const freq = String(abs % Math.pow(10,d)).padStart(d,'0');
      const grouped = String(whole).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
      const num = d>0 ? (grouped + '.' + freq) : grouped;
      const body = pos==='after' ? (num + ' ' + sym) : (sym + num);
      return (neg?'-':'') + body;
    }
  };
  window.IGN_Money = Money;

  // ---- Layout constants (A4 portrait, millimetres) ----
  const PAGE = { w:210, h:297, ml:16, mr:16, mt:16, mb:18 };
  const CONTENT_W = PAGE.w - PAGE.ml - PAGE.mr;
  const INK = [17,28,48], MUTED = [91,105,130], LINE = [229,233,242];

  function ready(){ return typeof window.jspdf !== 'undefined' && window.jspdf.jsPDF; }

  /* data = {
       title, number, brand, contact, toLabel, to, deliverTo, meta:[[label,value]...],
       columns:[{key,label,align,width}], rows:[{cells:[...]}],
       totals:[[label,value,strong]], notes:[[heading,text]...],
       accent:[r,g,b], logo:dataURL|null, showSignature, signatureLabel, notice
     } */
  function build(data){
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ unit:'mm', format:'a4', compress:true });
    const accent = data.accent || [16,36,73];

    let y = PAGE.mt;
    let page = 1;

    function setFont(style, size, colour){
      doc.setFont('helvetica', style||'normal');
      doc.setFontSize(size||10);
      const c = colour||INK;
      doc.setTextColor(c[0],c[1],c[2]);
    }
    function hr(yy){
      doc.setDrawColor(LINE[0],LINE[1],LINE[2]);
      doc.setLineWidth(0.2);
      doc.line(PAGE.ml, yy, PAGE.w-PAGE.mr, yy);
    }

    // ---------- Header block (first page only) ----------
    function drawDocumentHeader(){
      let left = PAGE.ml, right = PAGE.w - PAGE.mr;
      let yTop = y;

      if(data.logo){
        try{
          const props = doc.getImageProperties(data.logo);
          const maxW = 42, maxH = 18;
          let w = props.width, h = props.height;
          const scale = Math.min(maxW/w, maxH/h);
          w = w*scale; h = h*scale;
          doc.addImage(data.logo, props.fileType||'PNG', left, y, w, h);
          y += h + 3;
        }catch(e){ /* an unreadable logo must never stop the document */ }
      }

      setFont('bold', 12, accent);
      doc.text(String(data.brand||''), left, y+1);
      setFont('bold', 20, accent);
      doc.text(String(data.title||'INVOICE'), left, y+10);
      setFont('bold', 10, INK);
      if(data.number) doc.text(String(data.number), left, y+16);

      // contact lines under the brand
      let ly = y + 22;
      setFont('normal', 8.5, MUTED);
      (data.contact||[]).forEach(function(line){
        doc.text(String(line), left, ly); ly += 4;
      });

      // right column: recipient + meta
      let ry = yTop + 1;
      setFont('bold', 9, INK);
      if(data.toLabel){ doc.text(String(data.toLabel), right, ry, {align:'right'}); ry += 4.5; }
      setFont('normal', 8.5, MUTED);
      (data.to||[]).forEach(function(line){
        doc.text(String(line), right, ry, {align:'right'}); ry += 4;
      });
      if((data.deliverTo||[]).length){
        ry += 1.5; setFont('bold', 9, INK);
        doc.text('Deliver to', right, ry, {align:'right'}); ry += 4.5;
        setFont('normal', 8.5, MUTED);
        data.deliverTo.forEach(function(line){ doc.text(String(line), right, ry, {align:'right'}); ry += 4; });
      }
      ry += 1.5;
      (data.meta||[]).forEach(function(pair){
        setFont('bold', 8.5, INK);
        const label = String(pair[0]) + ': ';
        const value = String(pair[1]||'');
        const lw = doc.getTextWidth(label);
        const vw = doc.getTextWidth(value);
        doc.text(label, right - vw - lw + lw, ry, {align:'right'});
        setFont('normal', 8.5, MUTED);
        doc.text(value, right, ry, {align:'right'});
        setFont('bold', 8.5, INK);
        doc.text(label.trim(), right - vw - 1, ry, {align:'right'});
        ry += 4.2;
      });

      y = Math.max(ly, ry) + 4;
      hr(y); y += 6;
    }

    // ---------- Column header row (repeated on every page) ----------
    const cols = data.columns||[];
    function colX(i){
      let x = PAGE.ml;
      for(let k=0;k<i;k++) x += cols[k].width;
      return x;
    }
    function drawColumnHeader(){
      doc.setFillColor(244,247,252);
      doc.rect(PAGE.ml, y-4.5, CONTENT_W, 7, 'F');
      setFont('bold', 7.5, MUTED);
      cols.forEach(function(c,i){
        const x = c.align==='right' ? colX(i)+c.width-1.5 : colX(i)+1.5;
        doc.text(String(c.label).toUpperCase(), x, y, {align: c.align==='right'?'right':'left'});
      });
      y += 4;
      doc.setDrawColor(accent[0],accent[1],accent[2]);
      doc.setLineWidth(0.4);
      doc.line(PAGE.ml, y, PAGE.w-PAGE.mr, y);
      y += 4;
    }

    // ---------- Page break with repeated header ----------
    function footerFor(pageNo, totalPages){
      setFont('normal', 7.5, MUTED);
      const label = 'Page ' + pageNo + ' of ' + totalPages;
      doc.text(label, PAGE.w - PAGE.mr, PAGE.h - 10, {align:'right'});
      if(data.footerNote) doc.text(String(data.footerNote), PAGE.ml, PAGE.h - 10);
    }
    function needSpace(mm){
      if(y + mm <= PAGE.h - PAGE.mb) return;
      doc.addPage(); page += 1; y = PAGE.mt;
      setFont('normal', 8, MUTED);
      doc.text(String(data.title||'') + (data.number? ' ' + data.number : '') + ' (continued)', PAGE.ml, y);
      y += 6;
      drawColumnHeader();
    }

    // ---------- Draw ----------
    drawDocumentHeader();
    if(cols.length) drawColumnHeader();

    (data.rows||[]).forEach(function(row){
      // measure the tallest wrapped cell so a row never splits across pages
      let maxLines = 1;
      const wrapped = row.cells.map(function(cell,i){
        const w = cols[i].width - 3;
        const lines = doc.splitTextToSize(String(cell==null?'':cell), w);
        if(lines.length>maxLines) maxLines = lines.length;
        return lines;
      });
      const rowH = maxLines*4.2 + 3.4;
      needSpace(rowH + 4);

      setFont('normal', 9, INK);
      wrapped.forEach(function(lines,i){
        const c = cols[i];
        const x = c.align==='right' ? colX(i)+c.width-1.5 : colX(i)+1.5;
        if(c.bold) setFont('bold', 9, INK); else setFont('normal', 9, INK);
        doc.text(lines, x, y, {align: c.align==='right'?'right':'left'});
      });
      y += rowH;
      doc.setDrawColor(LINE[0],LINE[1],LINE[2]);
      doc.setLineWidth(0.15);
      doc.line(PAGE.ml, y-2.6, PAGE.w-PAGE.mr, y-2.6);
    });

    // ---------- Totals: kept together, never orphaned ----------
    const totals = data.totals||[];
    if(totals.length){
      const blockH = totals.length*5.5 + 8;
      needSpace(blockH);
      y += 3;
      const boxL = PAGE.w - PAGE.mr - 74;
      totals.forEach(function(t){
        const strong = !!t[2];
        if(strong){
          doc.setDrawColor(accent[0],accent[1],accent[2]);
          doc.setLineWidth(0.5);
          doc.line(boxL, y-3.4, PAGE.w-PAGE.mr, y-3.4);
        }
        setFont(strong?'bold':'normal', strong?11:9, strong?INK:MUTED);
        doc.text(String(t[0]), boxL, y);
        setFont(strong?'bold':'normal', strong?11:9, INK);
        doc.text(String(t[1]), PAGE.w-PAGE.mr, y, {align:'right'});
        y += strong?6.5:5.2;
      });
    }

    // ---------- Notice, notes, signature ----------
    if(data.notice){
      needSpace(12); y += 3;
      setFont('normal', 8.5, MUTED);
      doc.text(doc.splitTextToSize(String(data.notice), CONTENT_W), PAGE.ml, y);
      y += 6;
    }
    (data.notes||[]).forEach(function(n){
      const lines = doc.splitTextToSize(String(n[1]||''), CONTENT_W);
      needSpace(lines.length*4.2 + 10);
      y += 4;
      setFont('bold', 8, MUTED);
      doc.text(String(n[0]).toUpperCase(), PAGE.ml, y); y += 4.5;
      setFont('normal', 9, INK);
      doc.text(lines, PAGE.ml, y); y += lines.length*4.2;
    });
    if(data.showSignature){
      needSpace(22); y += 10;
      doc.setDrawColor(INK[0],INK[1],INK[2]); doc.setLineWidth(0.3);
      doc.line(PAGE.w-PAGE.mr-64, y, PAGE.w-PAGE.mr, y);
      setFont('normal', 8.5, MUTED);
      doc.text(String(data.signatureLabel||'Signature'), PAGE.w-PAGE.mr-64, y+4.5);
    }

    // ---------- Stamp "Page X of Y" on every page once the count is known ----------
    const total = doc.getNumberOfPages();
    for(let p=1;p<=total;p++){ doc.setPage(p); footerFor(p, total); }

    return doc;
  }

  window.IGN_PDF = {
    available: ready,
    build: build,
    save: function(data, filename){
      const doc = build(data);
      doc.save(filename || 'document.pdf');
    },
    open: function(data){
      const doc = build(data);
      const url = doc.output('bloburl');
      const w = window.open(url, '_blank');
      if(!w){ doc.save((data.number||'document') + '.pdf'); } // popup blocked: download instead
    }
  };
})();
