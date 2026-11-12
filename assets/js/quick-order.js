/**
 * Quick order: the form is fetched on each click and shown as chosen, with a light phone mask
 * that follows the configured Bulgarian format. The settings come from window.nashenskiQuickOrder
 * (Front_End\Quick_Order).
 */
(function(){var cfg=window.nashenskiQuickOrder;
var open=null,box=null;
function css(){
  if(document.getElementById("nashenski-quick-order-css")){return;}
  var s=document.createElement("style");
  s.id="nashenski-quick-order-css";
  s.textContent=".nashenski-quick-order{border:0;border-radius:8px;padding:24px;width:min(440px,92vw);max-height:90vh;overflow:auto;box-shadow:0 10px 40px rgba(0,0,0,.25);background:#fff}.nashenski-quick-order::backdrop{background:rgba(0,0,0,.45)}.nashenski-quick-order label{display:block}.nashenski-quick-order input:not([type=checkbox]),.nashenski-quick-order textarea{width:100%;box-sizing:border-box;margin-top:4px}.nashenski-quick-order h2{margin:0 0 8px}.nashenski-quick-order-actions{display:flex;gap:12px;align-items:center;justify-content:flex-start;margin-top:12px}.nashenski-quick-order--inline{position:static;box-shadow:none;border:1px solid rgba(0,0,0,.12);width:auto;margin:8px 0}.nashenski-quick-order--side{position:fixed;top:0;right:0;height:100%;width:min(440px,92vw);max-height:none;border-radius:0;z-index:100000;overflow:auto}.nashenski-quick-order__backdrop{position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:99999}";
  document.head.appendChild(s);
}
function close(){
  if(!box){return;}
  if(typeof box.close==="function"&&box.tagName==="DIALOG"){box.close();}
  if(box._backdrop){box._backdrop.remove();}
  box.remove();box=null;
}
function show(html,trigger){
  close();css();
  var a=cfg.appearance;
  if(a==="modal"){
    box=document.createElement("dialog");box.className="nashenski-quick-order";
    box.addEventListener("click",function(e){if(e.target===box){close();}});
    document.body.appendChild(box);box.innerHTML=html;box.showModal();
  } else if(a==="side"){
    box=document.createElement("div");box.className="nashenski-quick-order nashenski-quick-order--side";
    box._backdrop=document.createElement("div");box._backdrop.className="nashenski-quick-order__backdrop";
    box._backdrop.addEventListener("click",close);
    document.body.appendChild(box._backdrop);document.body.appendChild(box);box.innerHTML=html;
  } else {
    box=document.createElement("div");box.className="nashenski-quick-order nashenski-quick-order--inline";
    box.innerHTML=html;
    if(trigger&&trigger.parentNode){trigger.parentNode.insertBefore(box,trigger.nextSibling);}
    else{document.body.appendChild(box);}
  }
  var actions=box.querySelector(".nashenski-quick-order-actions");
  if(actions){actions.insertAdjacentHTML("beforeend","<button type='button' class='button nashenski-quick-order-close'>"+cfg.closeText+"</button>");}
  else{box.insertAdjacentHTML("beforeend","<p class='nashenski-quick-order-actions'><button type='button' class='button nashenski-quick-order-close'>"+cfg.closeText+"</button></p>");}
  var phone=box.querySelector("[data-nashenski-phone]");
  if(phone){mask(phone);phone.addEventListener("input",function(){mask(phone);});}
}
function mask(input){
  var d=input.value.replace(/[^0-9]/g,""),out="",dial=cfg.phoneDial||"";
  if(cfg.phoneFormat==="international"){
    if(dial&&d.indexOf(dial)!==0){d=(d.charAt(0)==="0")?dial+d.slice(1):dial+d;}
    var nat=d.slice(dial.length,20),parts=nat.match(/\d{1,4}/g)||[];
    out="+"+dial+(parts.length?" "+parts.join(" "):"");
  } else {
    if(d.charAt(0)!=="0"){d="0"+d;}
    var p=d.slice(0,14).match(/\d{1,4}/g)||[];
    out=p.join(" ");
  }
  if(input.value!==out){input.value=out;}
}
document.addEventListener("click",function(e){
  var btn=e.target.closest(".nashenski-quick-order-open");
  if(btn){
    e.preventDefault();open=btn;
    var qty=document.querySelector("form.cart input.qty");
    var data={action:"nashenski_quick_order_form",context:btn.getAttribute("data-context")||"product",product_id:btn.getAttribute("data-product-id")||"0",quantity:qty?qty.value:"1"};
    // The nonce is asked for now: one printed in a cached page would be stale.
    fetch(cfg.ajax,{method:"POST",credentials:"same-origin",body:new URLSearchParams({action:"nashenski_quick_order_nonce"})}).then(function(r){return r.json();}).then(function(res){
      data._nonce=res&&res.success?res.data:"";
      return fetch(cfg.ajax,{method:"POST",credentials:"same-origin",body:new URLSearchParams(data)});
    }).then(function(r){return r.text();}).then(function(html){show(html,btn);}).catch(function(){show("<p>"+cfg.errorText+"</p>",btn);});
    return;
  }
  if(e.target.closest(".nashenski-quick-order-close")){close();}
});
document.addEventListener("submit",function(e){
  var form=e.target;
  if(!form.closest||!form.closest(".nashenski-quick-order")){return;}
  e.preventDefault();
  var button=form.querySelector("button[type=submit]");
  if(button){button.disabled=true;}
  fetch(cfg.ajax,{method:"POST",credentials:"same-origin",body:new URLSearchParams(new FormData(form))}).then(function(r){return r.json();}).then(function(res){
    var msg=res&&res.data&&res.data.message?res.data.message:cfg.errorText;
    if(res&&res.success){if(box){box.innerHTML="<p>"+msg+"</p><p><button type='button' class='button nashenski-quick-order-close'>"+cfg.closeText+"</button></p>";}}
    else{if(button){button.disabled=false;}var note=form.querySelector(".nashenski-quick-order-error");if(note){note.textContent=msg;}}
  }).catch(function(){if(button){button.disabled=false;}});
});
})();
