document.addEventListener("DOMContentLoaded",()=>{const e=document.querySelector("[data-tablero-selector]");e&&e.addEventListener("change",()=>{e.value&&e.form.submit()})});
