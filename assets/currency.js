/* Labels are configured by the manager; protocol keys and amounts never change. */
(() => {
  const name = document.querySelector('meta[name="alphano-currency"]')?.content || 'Corone';
  const escaped = name.replace(/[&<>"']/gu, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  window.AlphanoCurrency = Object.freeze({
    name,
    text: text => String(text).replace(/\bcorone\b/giu, () => name),
    html: text => String(text).replace(/\bcorone\b/giu, () => escaped)
  });
})();
