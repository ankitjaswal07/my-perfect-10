/**
 * Perfect 10 v2.1.0 — Interactive Front-End Engine.
 *
 * Includes:
 *  1. Sticky header & top gold scroll-progress bar
 *  2. Responsive mobile drawer (without hiding the desktop top menu!)
 *  3. Seamless marquee ticker
 *  4. Smooth scroll-reveal & subtle hero parallax
 *  5. 3D / SD perspective card tilt & cursor spotlight glare ([data-p10-tilt])
 *  6. County -> City cascade
 *  7. Direct Search + County/City routing + Instant Live Autocomplete (/perfect10/v1/suggest)
 *  8. Left-side directory filters & live range output
 *  9. Sort selector
 * 10. Single-profile Gallery Image Switching (clears srcset/sizes, keyboard, swipe) + Fullscreen Lightbox!
 * 11. 18+ Age gate, smooth anchors, stat count-up, copy phone & hero video
 */
(function () {
  'use strict';

  var cfg = window.p10Config || {};
  var root = document.documentElement;

  /* ---------------------------------------------------------------- utils */

  function on(el, ev, fn, opts) {
    if (!el) return;
    el.addEventListener(ev, fn, opts || false);
  }

  function all(selector, ctx) {
    return Array.prototype.slice.call((ctx || document).querySelectorAll(selector));
  }

  function setCookie(name, value, days) {
    var d = new Date();
    d.setTime(d.getTime() + days * 864e5);
    document.cookie = name + '=' + encodeURIComponent(value) +
      ';expires=' + d.toUTCString() + ';path=/;SameSite=Lax';
  }

  function prefersReducedMotion() {
    return !!cfg.reducedMotion ||
      (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
  }

  /* ------------------------------------------ 1. header & scroll progress */

  function initHeader() {
    var header = document.querySelector('[data-p10-header]');
    var progressBar = document.createElement('div');
    progressBar.className = 'p10-scroll-progress';
    progressBar.setAttribute('aria-hidden', 'true');
    document.body.appendChild(progressBar);

    var heroImg = document.querySelector('.p10-hero__img');
    var lastY = window.pageYOffset;
    var ticking = false;

    function update() {
      var y = window.pageYOffset;
      var docH = Math.max(1, document.documentElement.scrollHeight - window.innerHeight);
      var ratio = Math.min(1, Math.max(0, y / docH));
      progressBar.style.transform = 'scaleX(' + ratio.toFixed(4) + ')';

      if (header) {
        header.classList.toggle('is-stuck', y > 36);
        if (y > 520 && y > lastY + 10) {
          header.classList.add('is-hidden');
        } else if (y < lastY - 8 || y < 240) {
          header.classList.remove('is-hidden');
        }
      }

      if (heroImg && !prefersReducedMotion() && y < window.innerHeight) {
        heroImg.style.setProperty('--p10-parallax-y', Math.round(y * 0.18) + 'px');
      }

      lastY = y;
      ticking = false;
    }

    on(window, 'scroll', function () {
      if (!ticking) {
        ticking = true;
        window.requestAnimationFrame(update);
      }
    }, { passive: true });

    update();
  }

  /* ------------------------------------------------------------ 2. drawer */

  function initDrawer() {
    var burger = document.querySelector('[data-p10-burger]');
    var drawer = document.querySelector('[data-p10-drawer]');
    if (!burger || !drawer) return;

    function setOpen(open) {
      burger.setAttribute('aria-expanded', open ? 'true' : 'false');
      drawer.classList.toggle('is-open', open);
      drawer.setAttribute('aria-hidden', open ? 'false' : 'true');
      document.body.classList.toggle('p10-drawer-open', open);
      root.style.overflow = open ? 'hidden' : '';
    }

    on(burger, 'click', function () {
      setOpen(burger.getAttribute('aria-expanded') !== 'true');
    });

    on(document, 'keydown', function (e) {
      if (e.key === 'Escape' && drawer.classList.contains('is-open')) {
        setOpen(false);
        burger.focus();
      }
    });

    // Always keep the desktop top menu visible on desktop viewports (> 1024px)
    // and ensure resizing back to desktop closes the mobile drawer cleanly.
    on(window, 'resize', function () {
      document.body.classList.remove('p10-compact-nav');
      if (window.innerWidth > 1024 && drawer.classList.contains('is-open')) {
        setOpen(false);
      }
    });

    all('a', drawer).forEach(function (link) {
      on(link, 'click', function () { setOpen(false); });
    });
  }

  /* ------------------------------------------------------------ 3. marquee */

  function initMarquee() {
    all('[data-p10-marquee]').forEach(function (marquee) {
      var track = marquee.querySelector('.p10-marquee__track');
      if (!track || track.dataset.cloned) return;

      var group = track.firstElementChild;
      if (group) {
        track.appendChild(group.cloneNode(true));
        track.dataset.cloned = '1';
      }
      if (prefersReducedMotion()) {
        track.style.animation = 'none';
      }
    });
  }

  /* ------------------------------------------------------------- 4. reveal */

  function initReveal() {
    var items = all('.p10-reveal');
    if (!items.length) return;

    if (prefersReducedMotion() || !('IntersectionObserver' in window)) {
      items.forEach(function (el) { el.classList.add('is-visible'); });
      return;
    }

    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          observer.unobserve(entry.target);
        }
      });
    }, { rootMargin: '0px 0px -6% 0px', threshold: 0.06 });

    items.forEach(function (el) { observer.observe(el); });
  }

  /* -------------------------------------- 5. 3D / SD perspective card tilt */

  function init3DTilt() {
    if (prefersReducedMotion()) return;
    if (window.matchMedia && !window.matchMedia('(pointer: fine)').matches) return;

    var cards = all('[data-p10-tilt]');
    cards.forEach(function (card) {
      var frame = null;

      on(card, 'mousemove', function (e) {
        var rect = card.getBoundingClientRect();
        if (!rect.width || !rect.height) return;
        var px = (e.clientX - rect.left) / rect.width;
        var py = (e.clientY - rect.top) / rect.height;
        var rx = ((0.5 - py) * 7.5).toFixed(2);
        var ry = ((px - 0.5) * 8.5).toFixed(2);

        if (frame) window.cancelAnimationFrame(frame);
        frame = window.requestAnimationFrame(function () {
          card.classList.add('is-tilting');
          card.style.setProperty('--p10-rx', rx + 'deg');
          card.style.setProperty('--p10-ry', ry + 'deg');
          card.style.setProperty('--p10-gx', Math.round(px * 100) + '%');
          card.style.setProperty('--p10-gy', Math.round(py * 100) + '%');
        });
      });

      on(card, 'mouseleave', function () {
        if (frame) window.cancelAnimationFrame(frame);
        card.classList.remove('is-tilting');
        card.style.setProperty('--p10-rx', '0deg');
        card.style.setProperty('--p10-ry', '0deg');
      });
    });
  }

  /* -------------------------------------------- 6. county -> city cascade */

  function cityOptions(countySlug, selected) {
    var label = (cfg.i18n && cfg.i18n.allCities) || 'All Cities';
    var html = '<option value="">' + label + '</option>';
    var map = cfg.citiesByCounty || {};
    var groups = countySlug ? [map[countySlug]] : Object.keys(map).map(function (k) { return map[k]; });

    groups.forEach(function (group) {
      if (!group || !group.cities) return;
      if (!countySlug && Object.keys(map).length > 1) {
        html += '<optgroup label="' + group.label + '">';
      }
      group.cities.forEach(function (city) {
        var isSel = selected && city.slug === selected;
        html += '<option value="' + city.slug + '"' + (isSel ? ' selected' : '') + '>' + city.name + '</option>';
      });
      if (!countySlug && Object.keys(map).length > 1) {
        html += '</optgroup>';
      }
    });

    return html;
  }

  function initCityCascade() {
    var counties = all('[data-p10-county]');
    if (!counties.length) return;

    counties.forEach(function (countySelect) {
      var scope = countySelect.closest('form') || document;
      var citySelect = scope.querySelector('[data-p10-city]');

      on(countySelect, 'change', function () {
        if (!citySelect) return;
        var previous = citySelect.value;
        citySelect.innerHTML = cityOptions(countySelect.value, '');
        if (previous && citySelect.querySelector('option[value="' + previous + '"]')) {
          citySelect.value = previous;
        }
        citySelect.dispatchEvent(new Event('p10:citychange', { bubbles: true }));
      });
    });

    all('[data-p10-city]').forEach(function (citySelect) {
      var preset = citySelect.getAttribute('data-county');
      if (preset && citySelect.options.length <= 1) {
        citySelect.innerHTML = cityOptions(preset, citySelect.value);
      }
    });
  }

  /* ----------------------- 7. Direct Search + Autocomplete + County/City */

  function initSearchForm() {
    all('[data-p10-search-form]').forEach(function (form) {
      var directInput = form.querySelector('[data-p10-direct-input]');
      var suggestBox = form.querySelector('[data-p10-suggest]');
      var debounceTimer = null;
      var abortCtrl = null;

      on(form, 'submit', function (e) {
        var county = form.querySelector('[data-p10-county]');
        var city = form.querySelector('[data-p10-city]');
        var qVal = directInput ? directInput.value.trim() : '';
        var countySlug = county ? county.value : '';
        var citySlug = city ? city.value : '';

        // When a direct keyword is entered, submit the GET form so ?q=...&county=...&city=... filters accurately!
        if (qVal) return;

        // If no county chosen, let standard GET form navigate to the directory.
        if (!countySlug) return;

        var tpl = form.getAttribute('data-url-template') || '';
        if (tpl.indexOf('__county__') === -1) return;

        e.preventDefault();
        var base = tpl.split('__county__')[0];
        var url = base + encodeURIComponent(countySlug) + '/';
        if (citySlug) url += encodeURIComponent(citySlug) + '/';
        window.location.href = url;
      });

      if (!directInput || !suggestBox || !cfg.restUrl) return;

      function hideSuggest() {
        suggestBox.hidden = true;
        suggestBox.innerHTML = '';
      }

      function renderSuggestions(items, queryText) {
        suggestBox.innerHTML = '';
        if (!items || !items.length) {
          hideSuggest();
          return;
        }

        items.forEach(function (item) {
          var a = document.createElement('a');
          a.className = 'p10-suggest__item';
          a.href = item.url;
          a.setAttribute('role', 'option');

          if (item.image) {
            var img = document.createElement('img');
            img.className = 'p10-suggest__thumb';
            img.src = item.image;
            img.alt = '';
            a.appendChild(img);
          }

          var meta = document.createElement('div');
          meta.className = 'p10-suggest__meta';

          var badge = document.createElement('span');
          badge.className = 'p10-suggest__badge';
          badge.textContent = item.badge || '';
          meta.appendChild(badge);

          var title = document.createElement('strong');
          title.className = 'p10-suggest__title';
          title.textContent = item.title || '';
          meta.appendChild(title);

          if (item.sub) {
            var sub = document.createElement('span');
            sub.className = 'p10-suggest__sub';
            sub.textContent = item.sub;
            meta.appendChild(sub);
          }

          a.appendChild(meta);
          suggestBox.appendChild(a);
        });

        var allLink = document.createElement('button');
        allLink.type = 'submit';
        allLink.className = 'p10-suggest__all';
        allLink.textContent = 'Search all results for “' + queryText + '” →';
        suggestBox.appendChild(allLink);

        suggestBox.hidden = false;
      }

      on(directInput, 'input', function () {
        var val = directInput.value.trim();
        window.clearTimeout(debounceTimer);
        if (abortCtrl) abortCtrl.abort();

        if (val.length < 1) {
          hideSuggest();
          return;
        }

        debounceTimer = window.setTimeout(function () {
          abortCtrl = new AbortController();
          var endpoint = cfg.restUrl.replace(/\/$/, '') + '/suggest?q=' + encodeURIComponent(val);
          fetch(endpoint, { signal: abortCtrl.signal, credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
              if (data && data.items) {
                renderSuggestions(data.items, val);
              } else {
                hideSuggest();
              }
            })
            .catch(function () {});
        }, 160);
      });

      on(directInput, 'keydown', function (e) {
        if (e.key === 'Escape') hideSuggest();
      });

      on(document, 'click', function (e) {
        if (!form.contains(e.target)) hideSuggest();
      });
    });
  }

  /* ---------------------------------------------------- 8. filter sidebar */

  function initFilters() {
    var form = document.querySelector('[data-p10-filters]');
    if (!form) return;

    on(form, 'change', function (e) {
      var target = e.target;
      if (!target) return;
      if (target.type === 'range' || target.type === 'search' || target.type === 'number') return;
      // When switching County in the sidebar, repopulate City first before submitting.
      if (target.hasAttribute('data-p10-county')) {
        var citySel = form.querySelector('[data-p10-city]');
        if (citySel) citySel.value = '';
      }
      form.submit();
    });

    all('input[type="range"]', form).forEach(function (range) {
      var wrap = range.closest('.p10-range');
      var out = wrap && wrap.querySelector('[data-p10-range-out]');
      function sync() {
        if (!out) return;
        var v = parseInt(range.value, 10);
        out.textContent = (!v || v >= 2000) ? 'Any' : ('$' + v.toLocaleString());
      }
      on(range, 'input', sync);
      on(range, 'change', function () { sync(); form.submit(); });
    });

    var reset = form.querySelector('[data-p10-filters-reset]');
    on(reset, 'click', function (e) {
      e.preventDefault();
      window.location.href = reset.getAttribute('href') || window.location.pathname;
    });
  }

  /* -------------------------------------------------------- 9. sort select */

  function initSort() {
    all('[data-p10-sort]').forEach(function (select) {
      on(select, 'change', function () {
        var url = new URL(window.location.href);
        if (select.value) {
          url.searchParams.set('sort', select.value);
        } else {
          url.searchParams.delete('sort');
        }
        url.searchParams.delete('paged');
        window.location.href = url.toString();
      });
    });
  }

  /* ------------------------- 10. Profile Gallery Switching + Lightbox */

  function initGallery() {
    var galleries = all('[data-p10-gallery]');
    if (!galleries.length) return;

    // Shared fullscreen lightbox dialog created lazily once.
    var lightbox = null;
    var lbImg = null;
    var lbCount = null;
    var activeGalleryApi = null;

    function ensureLightbox() {
      if (lightbox) return;
      lightbox = document.createElement('div');
      lightbox.className = 'p10-lightbox';
      lightbox.hidden = true;
      lightbox.setAttribute('role', 'dialog');
      lightbox.setAttribute('aria-modal', 'true');
      lightbox.setAttribute('aria-label', 'Fullscreen portrait viewer');
      lightbox.innerHTML =
        '<button type="button" class="p10-lightbox__close" data-lb-close aria-label="Close fullscreen viewer">×</button>' +
        '<button type="button" class="p10-lightbox__nav p10-lightbox__nav--prev" data-lb-prev aria-label="Previous portrait">←</button>' +
        '<figure class="p10-lightbox__stage"><img class="p10-lightbox__img" alt=""><figcaption class="p10-lightbox__caption" data-lb-count></figcaption></figure>' +
        '<button type="button" class="p10-lightbox__nav p10-lightbox__nav--next" data-lb-next aria-label="Next portrait">→</button>';
      document.body.appendChild(lightbox);

      lbImg = lightbox.querySelector('.p10-lightbox__img');
      lbCount = lightbox.querySelector('[data-lb-count]');

      on(lightbox.querySelector('[data-lb-close]'), 'click', closeLightbox);
      on(lightbox.querySelector('[data-lb-prev]'), 'click', function () {
        if (activeGalleryApi) activeGalleryApi.step(-1);
      });
      on(lightbox.querySelector('[data-lb-next]'), 'click', function () {
        if (activeGalleryApi) activeGalleryApi.step(1);
      });
      on(lightbox, 'click', function (e) {
        if (e.target === lightbox || e.target.classList.contains('p10-lightbox__stage')) {
          closeLightbox();
        }
      });
      on(document, 'keydown', function (e) {
        if (!lightbox || lightbox.hidden) return;
        if (e.key === 'Escape') closeLightbox();
        if (e.key === 'ArrowLeft' && activeGalleryApi) activeGalleryApi.step(-1);
        if (e.key === 'ArrowRight' && activeGalleryApi) activeGalleryApi.step(1);
      });
    }

    function closeLightbox() {
      if (!lightbox) return;
      lightbox.hidden = true;
      root.style.overflow = '';
    }

    galleries.forEach(function (gallery) {
      var mainWrap = gallery.querySelector('[data-p10-gallery-main]');
      var main = gallery.querySelector('[data-p10-gallery-stage]') || (mainWrap && mainWrap.querySelector('img'));
      var thumbs = all('[data-p10-gallery-thumb]', gallery);
      var counter = gallery.querySelector('[data-p10-gallery-count]');
      var prevBtn = gallery.querySelector('[data-p10-gallery-prev]');
      var nextBtn = gallery.querySelector('[data-p10-gallery-next]');
      var zoomBtn = gallery.querySelector('[data-p10-gallery-zoom]');
      var index = 0;
      var touchStartX = 0;

      if (!main) return;

      function syncLightbox() {
        if (!lightbox || lightbox.hidden || !lbImg) return;
        var zoomSrc = main.getAttribute('data-zoom') || main.src;
        lbImg.src = zoomSrc;
        lbImg.alt = main.alt || '';
        if (lbCount && thumbs.length > 1) {
          lbCount.textContent = (index + 1) + ' / ' + thumbs.length + ' — ' + (main.alt || '');
        } else if (lbCount) {
          lbCount.textContent = main.alt || '';
        }
      }

      function show(i, immediate) {
        if (!thumbs.length) return;
        index = ((i % thumbs.length) + thumbs.length) % thumbs.length;
        var thumb = thumbs[index];
        var thumbImg = thumb.querySelector('img');
        var full = thumb.getAttribute('data-full') || (thumbImg ? thumbImg.src : '');
        var zoom = thumb.getAttribute('data-zoom') || full;
        var alt = thumb.getAttribute('data-alt') || main.alt || '';

        if (full) {
          var applySwap = function () {
            // CRITICAL FIX: Remove responsive srcset/sizes attributes so browsers
            // immediately render the newly selected portrait rather than keeping image #1!
            main.removeAttribute('srcset');
            main.removeAttribute('sizes');
            main.src = full;
            main.setAttribute('data-zoom', zoom);
            main.alt = alt;
            main.classList.remove('is-switching');
            syncLightbox();
          };

          if (immediate || prefersReducedMotion() || main.src === full) {
            applySwap();
          } else {
            main.classList.add('is-switching');
            window.setTimeout(applySwap, 110);
          }
        }

        thumbs.forEach(function (t, n) {
          var active = n === index;
          t.classList.toggle('is-active', active);
          t.setAttribute('aria-current', active ? 'true' : 'false');
        });

        if (counter) {
          counter.textContent = (index + 1) + ' / ' + thumbs.length;
        }
      }

      var api = {
        step: function (delta) { show(index + delta, false); }
      };

      thumbs.forEach(function (thumb, i) {
        on(thumb, 'click', function (e) {
          e.preventDefault();
          show(i, false);
        });
      });

      on(prevBtn, 'click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        show(index - 1, false);
      });

      on(nextBtn, 'click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        show(index + 1, false);
      });

      function openLightbox() {
        ensureLightbox();
        activeGalleryApi = api;
        lightbox.hidden = false;
        root.style.overflow = 'hidden';
        syncLightbox();
      }

      on(zoomBtn, 'click', function (e) {
        e.preventDefault();
        openLightbox();
      });

      on(main, 'click', function () {
        openLightbox();
      });

      on(gallery, 'keydown', function (e) {
        if (e.key === 'ArrowLeft') { e.preventDefault(); show(index - 1, false); }
        if (e.key === 'ArrowRight') { e.preventDefault(); show(index + 1, false); }
      });

      on(mainWrap, 'touchstart', function (e) {
        if (e.touches && e.touches.length === 1) {
          touchStartX = e.touches[0].clientX;
        }
      }, { passive: true });

      on(mainWrap, 'touchend', function (e) {
        if (!touchStartX || !e.changedTouches || !e.changedTouches.length) return;
        var dx = e.changedTouches[0].clientX - touchStartX;
        touchStartX = 0;
        if (Math.abs(dx) > 40) {
          show(dx < 0 ? index + 1 : index - 1, false);
        }
      }, { passive: true });

      if (thumbs.length) {
        show(0, true);
      }
    });
  }

  /* --------------------------------------------------------- 11. age gate */

  function initAgeGate() {
    var gate = document.querySelector('[data-p10-agegate]');
    if (!gate) return;

    var accept = gate.querySelector('[data-p10-agegate-accept]');

    function unlock() {
      setCookie('p10_age_ok', '1', 30);
      gate.style.transition = 'opacity 420ms ease';
      gate.style.opacity = '0';
      document.body.classList.remove('p10-agegate-open');
      root.style.overflow = '';
      window.setTimeout(function () {
        if (gate.parentNode) gate.parentNode.removeChild(gate);
      }, prefersReducedMotion() ? 0 : 420);
      document.body.classList.remove('p10-blur');
      document.dispatchEvent(new Event('p10:ageaccepted'));
    }

    on(accept, 'click', unlock);
    on(document, 'keydown', function (e) {
      if (e.key === 'Enter' && document.activeElement === document.body) unlock();
    });

    if (accept) window.setTimeout(function () { accept.focus(); }, 60);
  }

  /* ------------------------------------------------- 12. offset anchors */

  function initAnchors() {
    on(document, 'click', function (e) {
      var link = e.target.closest ? e.target.closest('a[href^="#"]') : null;
      if (!link) return;

      var id = link.getAttribute('href');
      if (!id || id === '#') return;

      var target = document.querySelector(id);
      if (!target) return;

      e.preventDefault();
      var headerH = (document.querySelector('[data-p10-header]') || {}).offsetHeight || 76;
      var top = target.getBoundingClientRect().top + window.pageYOffset - headerH - 16;

      window.scrollTo({ top: Math.max(top, 0), behavior: prefersReducedMotion() ? 'auto' : 'smooth' });

      if (history.replaceState) history.replaceState(null, '', id);
    });
  }

  /* ------------------------------------------- 13. live count-up for stats */

  function initCountUp() {
    if (prefersReducedMotion()) return;

    var stats = all('[data-p10-count]');
    if (!stats.length || !('IntersectionObserver' in window)) return;

    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        observer.unobserve(entry.target);

        var el = entry.target;
        var target = parseInt(el.getAttribute('data-p10-count'), 10);
        if (isNaN(target)) return;

        var suffix = el.getAttribute('data-p10-suffix') || '';
        var duration = 1100;
        var start = null;

        function step(ts) {
          if (!start) start = ts;
          var p = Math.min((ts - start) / duration, 1);
          var eased = 1 - Math.pow(1 - p, 3);
          el.textContent = Math.round(target * eased) + suffix;
          if (p < 1) window.requestAnimationFrame(step);
        }
        window.requestAnimationFrame(step);
      });
    }, { threshold: 0.4 });

    stats.forEach(function (el) { observer.observe(el); });
  }

  /* ------------------------------------------------------- 14. copy phone */

  function initCopyPhone() {
    all('[data-p10-copy]').forEach(function (btn) {
      on(btn, 'click', function (e) {
        e.preventDefault();
        var text = btn.getAttribute('data-p10-copy');
        if (!text || !navigator.clipboard) return;
        navigator.clipboard.writeText(text).then(function () {
          var original = btn.textContent;
          btn.textContent = (cfg.i18n && cfg.i18n.copied) || 'Copied';
          window.setTimeout(function () { btn.textContent = original; }, 1600);
        });
      });
    });
  }

  /* -------------------------------------------------------- 15. hero video */

  function initHeroVideo() {
    var media = document.querySelectorAll('[data-p10-hero-media]');
    if (!media.length) return;

    var canPlay = window.innerWidth > 767 && !prefersReducedMotion();
    var canObserve = 'IntersectionObserver' in window;

    media.forEach(function (wrap) {
      var type = wrap.getAttribute('data-p10-video-type');
      var src = wrap.getAttribute('data-p10-video-src');
      var id = wrap.getAttribute('data-p10-video-id');
      if (!type || !(src || id)) return;

      var video = wrap.querySelector('[data-p10-hero-video]');
      var embed = wrap.querySelector('[data-p10-hero-embed]');
      if (!video && !embed) return;

      var started = false;
      var playing = false;

      function start() {
        if (started) return;
        started = true;

        if (video) {
          var source = video.querySelector('source[data-src]');
          if (source) {
            source.setAttribute('src', source.getAttribute('data-src'));
            video.load();
          }
          var attempt = video.play();
          if (attempt && attempt.catch) attempt.catch(function () {});
          return;
        }

        if (embed && id) {
          var frame = document.createElement('iframe');
          frame.setAttribute('title', '');
          frame.setAttribute('tabindex', '-1');
          frame.setAttribute('aria-hidden', 'true');
          frame.setAttribute('allow', 'autoplay; encrypted-media; picture-in-picture');
          frame.setAttribute('loading', 'lazy');
          frame.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
          if (type === 'youtube') {
            frame.src = 'https://www.youtube-nocookie.com/embed/' + encodeURIComponent(id) +
              '?autoplay=1&mute=1&controls=0&loop=1&playlist=' + encodeURIComponent(id) +
              '&playsinline=1&modestbranding=1&rel=0&iv_load_policy=3&disablekb=1';
          } else if (type === 'vimeo') {
            frame.src = 'https://player.vimeo.com/video/' + encodeURIComponent(id) +
              '?autoplay=1&muted=1&loop=1&background=1&dnt=1';
          } else {
            return;
          }
          embed.appendChild(frame);
        }

        wrap.classList.add('is-video-ready');
      }

      if (video) {
        on(video, 'playing', function () {
          playing = true;
          wrap.classList.add('is-video-ready');
          video.style.opacity = '1';
        });
        on(video, 'error', function () {
          wrap.classList.remove('is-video-ready');
          started = false;
        });
      }

      if (!canPlay || !canObserve) return;

      var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            start();
            if (video && playing) {
              var p = video.play();
              if (p && p.catch) p.catch(function () {});
            }
          } else if (video) {
            video.pause();
          }
        });
      }, { rootMargin: '200px 0px', threshold: 0.01 });

      observer.observe(wrap);
    });
  }

  /* ------------------------------------------------------------- 16. boot */

  function boot() {
    if (prefersReducedMotion()) root.classList.add('p10-no-motion');

    initHeader();
    initDrawer();
    initMarquee();
    initReveal();
    init3DTilt();
    initCityCascade();
    initSearchForm();
    initFilters();
    initSort();
    initGallery();
    initAgeGate();
    initAnchors();
    initCountUp();
    initCopyPhone();
    initHeroVideo();

    document.dispatchEvent(new Event('p10:ready'));
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
