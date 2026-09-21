(function () {
  var nav = document.querySelector('header nav') || document.querySelector('nav');
  if (!nav || nav.querySelector('.coc-menu-btn')) return;

  nav.classList.add('coc-nav');

  // Light headers (dark text) need a dark-text menu; nav sitting inside a flex row keeps its place beside the logo.
  function luminance(rgb) {
    var m = rgb.match(/[\d.]+/g);
    if (!m) return 1;
    var c = m.slice(0, 3).map(function (v) { v = Number(v) / 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); });
    return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
  }
  var el = nav, bg = 'rgba(0, 0, 0, 0)';
  while (el && el !== document.documentElement) {
    var cs = getComputedStyle(el), bi = cs.backgroundImage;
    if (bi && bi.indexOf('gradient') !== -1) {
      var gm = bi.match(/rgba?\([^)]*\)/);
      if (gm) { bg = gm[0]; break; }
    }
    var b = cs.backgroundColor;
    if (b && !/rgba?\(\s*\d+,\s*\d+,\s*\d+,\s*0\s*\)/.test(b) && b !== 'transparent') { bg = b; break; }
    el = el.parentElement;
  }
  if (luminance(bg) > 0.55) nav.classList.add('coc-nav-light');
  var parent = nav.parentElement;
  if (parent && parent !== document.body && parent.tagName !== 'HEADER' && getComputedStyle(parent).display.indexOf('flex') !== -1) { nav.classList.add('coc-nav-inline'); parent.classList.add('coc-nav-parent'); }

  var btn = document.createElement('button');
  btn.type = 'button';
  btn.className = 'coc-menu-btn';
  btn.setAttribute('aria-expanded', 'false');
  btn.innerHTML = '<span class="coc-burger" aria-hidden="true"></span><span class="coc-menu-label">Menu</span>';
  nav.insertBefore(btn, nav.firstChild);

  function setOpen(open) {
    nav.classList.toggle('coc-open', open);
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    btn.querySelector('.coc-menu-label').textContent = open ? 'Close' : 'Menu';
  }

  btn.addEventListener('click', function () { setOpen(!nav.classList.contains('coc-open')); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setOpen(false); });
  window.addEventListener('resize', function () { if (window.innerWidth > 900) setOpen(false); });
})();
