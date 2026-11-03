(function () {
  "use strict";
@if (! $valido)
  console.debug("[RankPro Tracking] token invalido - snippet inactivo.");
  return;
@endif

  var API_BASE = @json($apiBase);
  var TOKEN = @json($token);
  var VID_KEY = "_rankpro_vid";
  var CLICKID_KEY = "_rankpro_clickid";
  var VID_DAYS = 730;
  var CLICKID_DAYS = 90; // ventana de atribucion tipica de Google Ads

  function uuid() {
    if (window.crypto && window.crypto.randomUUID) return window.crypto.randomUUID();
    // Respaldo para navegadores viejos: getRandomValues es criptografico; Math.random solo como ultimo recurso.
    var bytes = null;
    try {
      if (window.crypto && window.crypto.getRandomValues) bytes = window.crypto.getRandomValues(new Uint8Array(32));
    } catch (e) {}
    var i = 0;
    return "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g, function (c) {
      var r = bytes ? bytes[i++] & 15 : (Math.random() * 16) | 0;
      var v = c === "x" ? r : (r & 0x3) | 0x8;
      return v.toString(16);
    });
  }

  function setCookie(name, value, days) {
    try {
      var expires = new Date(Date.now() + days * 864e5).toUTCString();
      document.cookie = name + "=" + encodeURIComponent(value) + "; expires=" + expires + "; path=/; SameSite=Lax";
    } catch (e) {}
  }

  function getCookie(name) {
    try {
      var match = document.cookie.match(new RegExp("(^| )" + name + "=([^;]+)"));
      return match ? decodeURIComponent(match[2]) : null;
    } catch (e) {
      return null;
    }
  }

  // localStorage primero, cookie como respaldo (Safari ITP puede limpiar uno u otro de forma inconsistente).
  function getStored(key) {
    try {
      var v = window.localStorage.getItem(key);
      if (v) return v;
    } catch (e) {}
    return getCookie(key);
  }

  function setStored(key, value, days) {
    try {
      window.localStorage.setItem(key, value);
    } catch (e) {}
    setCookie(key, value, days);
  }

  function getVisitorId() {
    var vid = getStored(VID_KEY);
    if (!vid) {
      vid = uuid();
      setStored(VID_KEY, vid, VID_DAYS);
    }
    return vid;
  }

  // Cada evento lleva su evento_id (cuerpo y header) y los reintentos reenvian el MISMO payload,
  // asi el servidor los descarta como duplicados en vez de contarlos otra vez.
  function post(path, payload, intento) {
    intento = intento || 0;
    if (!payload.evento_id) payload.evento_id = uuid();
    function reintentar() {
      if (intento >= 2) return;
      setTimeout(function () {
        post(path, payload, intento + 1);
      }, 1500 * (intento + 1));
    }
    try {
      fetch(API_BASE + path, {
        method: "POST",
        keepalive: true,
        headers: { "Content-Type": "application/json", "X-RankPro-Token": TOKEN, "X-RankPro-Evento-Id": payload.evento_id },
        body: JSON.stringify(payload),
      })
        .then(function (res) {
          if (res && res.status >= 500) reintentar();
        })
        .catch(reintentar);
    } catch (e) {}
  }

  function paramsFromUrl() {
    var params = {};
    try {
      var search = new URLSearchParams(window.location.search);
      ["gclid", "gbraid", "wbraid", "utm_source", "utm_medium", "utm_campaign", "utm_term", "utm_content"].forEach(function (key) {
        var v = search.get(key);
        if (v) params[key] = v;
      });
    } catch (e) {}
    return params;
  }

  var visitorId = getVisitorId();
  var urlParams = paramsFromUrl();
  var hasClickId = urlParams.gclid || urlParams.gbraid || urlParams.wbraid;

  if (hasClickId) {
    var clicPayload = { visitor_id: visitorId, landing_url: window.location.href, referrer: document.referrer || "" };
    for (var key in urlParams) clicPayload[key] = urlParams[key];
    post("/clic", clicPayload);

    // Se guarda aparte del resto de UTMs porque es lo unico que trackConversion() necesita releer despues, quizas en otra pagina del sitio.
    setStored(
      CLICKID_KEY,
      JSON.stringify({ gclid: urlParams.gclid || "", gbraid: urlParams.gbraid || "", wbraid: urlParams.wbraid || "" }),
      CLICKID_DAYS
    );
  }

  window.RankProTracking = {
    trackConversion: function (tipo, valor) {
      var stored = {};
      try {
        stored = JSON.parse(getStored(CLICKID_KEY) || "{}");
      } catch (e) {}

      post("/conversion", {
        visitor_id: visitorId,
        gclid: stored.gclid || "",
        gbraid: stored.gbraid || "",
        wbraid: stored.wbraid || "",
        tipo: tipo,
        valor: valor === undefined ? null : valor,
      });
    },
  };
})();
