/* InvoiceGeneratorNow — export and share.
   Produces the document as a real PDF, PNG or JPG, and shares the actual FILE
   through the native share sheet (WhatsApp, Mail, Messages, AirDrop, Files).

   Why this exists: wa.me and mailto: links are URL protocols and can never carry an
   attachment. Only the Web Share API with a files array can. Everything below is built
   around that fact, and around iOS/iPadOS refusing downloads that are not the direct
   result of a tap. */
(function(){
  'use strict';

  // iPadOS reports itself as desktop Safari, so touch points are the reliable signal.
  const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent)
             || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  const canShareFiles = function(files){
    try { return !!(navigator.canShare && navigator.canShare({ files: files })); }
    catch(e){ return false; }
  };

  function blobToFile(blob, name){
    try { return new File([blob], name, { type: blob.type }); }
    catch(e){ blob.name = name; return blob; }
  }

  // Save a blob. On iOS the download attribute is ignored, so open it instead and let
  // the person use the system share sheet to keep it.
  function saveBlob(blob, filename){
    const url = URL.createObjectURL(blob);
    if(isIOS){
      const w = window.open(url, '_blank');
      if(!w){
        const a = document.createElement('a');
        a.href = url; a.target = '_blank'; a.rel = 'noopener';
        document.body.appendChild(a); a.click(); a.remove();
      }
    } else {
      const a = document.createElement('a');
      a.href = url; a.download = filename;
      document.body.appendChild(a); a.click(); a.remove();
    }
    setTimeout(function(){ URL.revokeObjectURL(url); }, 60000);
  }

  // ---- PDF ----
  function pdfBlob(payload){
    if(!(window.IGN_PDF && window.IGN_PDF.available())) return null;
    const doc = window.IGN_PDF.build(payload);
    return doc.output('blob');
  }

  // ---- Raster (PNG / JPG) ----
  // html2canvas does not understand the CSS `zoom` used to scale the on-screen preview,
  // which produced overlapping text. So we render from a clean, unscaled, fixed-width
  // clone placed offscreen. That also makes the image identical on every device.
  function rasterBlob(el, type, quality){
    return new Promise(function(resolve, reject){
      if(typeof html2canvas === 'undefined'){ reject(new Error('Image engine unavailable')); return; }
      const RENDER_W = 820; // fixed canvas width so output never depends on screen size

      const stage = document.createElement('div');
      stage.setAttribute('aria-hidden','true');
      stage.style.cssText = 'position:fixed;left:-10000px;top:0;width:'+RENDER_W+'px;'+
                            'background:#ffffff;z-index:-1;pointer-events:none;';
      const clone = el.cloneNode(true);
      // Strip anything that scales or clips the on-screen version
      clone.style.zoom = '1';
      clone.style.transform = 'none';
      clone.style.maxHeight = 'none';
      clone.style.height = 'auto';
      clone.style.overflow = 'visible';
      clone.style.width = RENDER_W + 'px';
      clone.style.maxWidth = RENDER_W + 'px';
      clone.style.margin = '0';
      clone.style.padding = '32px';
      clone.style.boxShadow = 'none';
      clone.style.border = '0';
      clone.style.background = '#ffffff';
      clone.classList.add('doc-raster');
      Array.prototype.forEach.call(clone.querySelectorAll('*'), function(n){
        if(n.style){ n.style.zoom = '1'; n.style.maxHeight = 'none'; n.style.overflow = 'visible'; }
      });
      stage.appendChild(clone);
      document.body.appendChild(stage);

      const cleanup = function(){ if(stage.parentNode) stage.parentNode.removeChild(stage); };

      // let layout settle before capture
      requestAnimationFrame(function(){
        html2canvas(clone, {
          scale: 2,
          backgroundColor: '#ffffff',
          useCORS: true,
          logging: false,
          width: RENDER_W,
          windowWidth: RENDER_W,
          scrollX: 0,
          scrollY: 0
        }).then(function(canvas){
          cleanup();
          canvas.toBlob(function(b){
            if(b) resolve(b); else reject(new Error('Could not create the image'));
          }, type, quality);
        }).catch(function(e){ cleanup(); reject(e); });
      });
    });
  }

  window.IGN_Export = {
    isIOS: isIOS,
    canShareFiles: canShareFiles,

    pdf: function(payload, filename){
      const b = pdfBlob(payload);
      if(!b) throw new Error('PDF engine unavailable');
      saveBlob(b, filename);
      return b;
    },

    png: function(el, filename){
      return rasterBlob(el, 'image/png').then(function(b){ saveBlob(b, filename); return b; });
    },

    jpg: function(el, filename){
      return rasterBlob(el, 'image/jpeg', 0.92).then(function(b){ saveBlob(b, filename); return b; });
    },

    /* Share the real document. format: 'pdf' | 'png' | 'jpg'
       Falls back to downloading if the platform cannot share files. */
    shareFile: function(opts){
      const format = opts.format || 'pdf';
      const name = opts.filename || ('document.' + format);
      const text = opts.text || '';
      const title = opts.title || 'Document';

      const makeBlob = (format === 'pdf')
        ? Promise.resolve(pdfBlob(opts.payload))
        : rasterBlob(opts.element, format === 'png' ? 'image/png' : 'image/jpeg', 0.92);

      return makeBlob.then(function(blob){
        if(!blob) throw new Error('Could not build the document');
        const file = blobToFile(blob, name);
        if(canShareFiles([file])){
          return navigator.share({ files: [file], title: title, text: text })
            .then(function(){ return 'shared'; })
            .catch(function(err){
              if(err && err.name === 'AbortError') return 'cancelled';
              saveBlob(blob, name);
              return 'downloaded';
            });
        }
        // No file sharing on this platform: hand over the file so it can be attached manually.
        saveBlob(blob, name);
        return 'downloaded';
      });
    }
  };
})();
