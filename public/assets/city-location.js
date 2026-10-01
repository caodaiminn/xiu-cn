(() => {
  const marker = document.querySelector('[data-ip-city-location]');
  if (!marker) return;
  try { if (sessionStorage.getItem('xiu-city-location-retry') > Date.now()) return; } catch { /* Storage can be disabled. */ }
  const getIp = async url => {
    const controller = new AbortController(); const timeout = setTimeout(() => controller.abort(), 3000);
    try { const response = await fetch(url, { signal: controller.signal, credentials: 'omit', referrerPolicy: 'no-referrer' }); if (!response.ok) throw new Error('IP unavailable'); const result = await response.json(); if (typeof result.ip !== 'string') throw new Error('Invalid IP'); return result.ip; }
    finally { clearTimeout(timeout); }
  };
  (async () => {
    try {
      let ip; try { ip = await getIp('https://api.ipify.org?format=json'); } catch { ip = await getIp('https://api64.ipify.org?format=json'); }
      const response = await fetch('/api/city-location/', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ csrf: marker.dataset.csrf, ip }) });
      if (!response.ok) throw new Error('Location unavailable');
      const result = await response.json();
      if (result.city) { location.reload(); return; }
    } catch { /* Keep the homepage usable and allow manual selection. */ }
    try { sessionStorage.setItem('xiu-city-location-retry', String(Date.now() + 1800000)); } catch { /* Manual city selection remains available. */ }
  })();
})();
