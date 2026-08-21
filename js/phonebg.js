/* global bootstrap */

(function () {
   'use strict';

   function initPhonePreview() {
      var buttons = document.querySelectorAll('[data-phonebg-preview]');
      if (!buttons.length) {
         return;
      }

      buttons.forEach(function (button) {
         if (button.dataset.phonebgBound === '1') {
            return;
         }
         button.dataset.phonebgBound = '1';

         button.addEventListener('click', function () {
            if (button.disabled) {
               return;
            }

            var phoneId = button.getAttribute('data-phone-id');
            var previewUrl = button.getAttribute('data-preview-url');
            var modalEl = document.getElementById('pb-preview-modal-' + phoneId);
            var body = document.getElementById('pb-modal-body-' + phoneId);

            if (!modalEl || !body || !previewUrl) {
               return;
            }

            body.textContent = '';
            var spinner = document.createElement('div');
            spinner.className = 'pb-modal-spinner';
            spinner.innerHTML = '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">...</span></div>';
            body.appendChild(spinner);

            var modal = new bootstrap.Modal(modalEl);
            modal.show();

            var onShown = function () {
               modalEl.removeEventListener('shown.bs.modal', onShown);

               var url = previewUrl + (previewUrl.indexOf('?') === -1 ? '?' : '&') + 't=' + Date.now();
               fetch(url, {
                  credentials: 'same-origin',
                  headers: {
                     'Accept': 'image/png, application/json'
                  }
               })
                                    .then(function (res) {
                      var contentType = res.headers.get('Content-Type') || '';

                      if (contentType.indexOf('application/json') !== -1) {
                         return res.json().then(function (data) {
                            if (!res.ok) {
                               throw new Error(data.error || 'Could not load preview (HTTP ' + res.status + ').');
                            }
                            return data;
                         });
                      }

                      if (!res.ok) {
                         throw new Error('Could not load preview (HTTP ' + res.status + ').');
                      }

                      return res.blob();
                   })
.then(function (blob) {
                     var objectUrl = URL.createObjectURL(blob);
                     var image = new Image();
                     image.onload = function () {
                        body.textContent = '';
                        image.style.maxWidth = '100%';
                        image.style.height = 'auto';
                        image.style.display = 'block';
                        image.style.margin = '0 auto';
                        body.appendChild(image);
                        URL.revokeObjectURL(objectUrl);
                     };
                     image.onerror = function () {
                        URL.revokeObjectURL(objectUrl);
                        showPreviewError(body, 'Could not load preview.');
                     };
                     image.src = objectUrl;
                  })
                  .catch(function (error) {
                     showPreviewError(body, error.message || 'Could not load preview.');
                  });
            };

            modalEl.addEventListener('shown.bs.modal', onShown);
         });
      });
   }

   function showPreviewError(container, message) {
      container.textContent = '';
      var alert = document.createElement('div');
      alert.className = 'alert alert-danger text-start';
      alert.textContent = message;
      container.appendChild(alert);
   }

   function initConfigActions() {
      var baseInput = document.getElementById('inp-base-file');
      if (baseInput && baseInput.dataset.phonebgBound !== '1') {
         baseInput.dataset.phonebgBound = '1';
         baseInput.addEventListener('change', function () {
            phonebgPreviewNewBase(this);
         });
      }

      document.querySelectorAll('[data-phonebg-confirm]').forEach(function (button) {
         if (button.dataset.phonebgBound === '1') {
            return;
         }
         button.dataset.phonebgBound = '1';
         button.addEventListener('click', function (event) {
            var message = button.getAttribute('data-phonebg-confirm') || '';
            if (message && !window.confirm(message)) {
               event.preventDefault();
               event.stopImmediatePropagation();
            }
         });
      });

      var lastFocus = null;
      ['inp-email-subject', 'inp-email-body', 'inp-email-footer'].forEach(function (id) {
         var el = document.getElementById(id);
         if (el) {
            el.addEventListener('focus', function () {
               lastFocus = el;
            });
         }
      });

      document.querySelectorAll('.pb-var-badge').forEach(function (badge) {
         if (badge.dataset.phonebgBound === '1') {
            return;
         }
         badge.dataset.phonebgBound = '1';
         badge.addEventListener('click', function () {
            var el = lastFocus;
            if (!el) {
               return;
            }
            var value = badge.dataset.var || '';
            var start = el.selectionStart;
            var end = el.selectionEnd;
            el.value = el.value.slice(0, start) + value + el.value.slice(end);
            el.selectionStart = el.selectionEnd = start + value.length;
            el.focus();
         });
      });

      document.addEventListener('click', function (event) {
         var button = event.target.closest('.pb-fmt-btn');
         if (!button) {
            return;
         }

         var wrap = button.dataset.wrap || '';
         var length = wrap.length;
         var textarea = document.activeElement && document.activeElement.tagName === 'TEXTAREA'
            ? document.activeElement
            : lastFocus;

         if (!textarea || textarea.tagName !== 'TEXTAREA' || !wrap) {
            return;
         }

         textarea.focus();
         var start = textarea.selectionStart;
         var end = textarea.selectionEnd;
         var selected = textarea.value.substring(start, end);
         var before = textarea.value.substring(start - length, start);
         var after = textarea.value.substring(end, end + length);

         if (before === wrap && after === wrap) {
            textarea.setRangeText(selected, start - length, end + length, 'preserve');
            textarea.selectionStart = start - length;
            textarea.selectionEnd = start - length + selected.length;
         } else if (
            selected.startsWith(wrap)
            && selected.endsWith(wrap)
            && selected.length >= length * 2 + 1
         ) {
            var inner = selected.slice(length, selected.length - length);
            textarea.setRangeText(inner, start, end, 'preserve');
            textarea.selectionStart = start;
            textarea.selectionEnd = start + inner.length;
         } else {
            var text = selected || 'text';
            textarea.setRangeText(wrap + text + wrap, start, end, 'preserve');
            textarea.selectionStart = start + length;
            textarea.selectionEnd = start + length + text.length;
         }
      });

      document.querySelectorAll('.pb-format-toolbar').forEach(function (bar) {
         bar.style.cssText = 'user-select:none';
      });
      document.querySelectorAll('.pb-fmt-btn').forEach(function (button) {
         button.style.cssText = 'padding:1px 7px;font-size:0.82em;line-height:1.4';
      });

      var wrap = document.getElementById('pb-editor-wrap');
      var image = document.getElementById('pb-template-img');
      if (!wrap || !image) {
         return;
      }

      var dirty = false;
      var form = document.querySelector('#phonebg-tab-positions form');
      function markDirty() {
         dirty = true;
      }

      function placeLabel(field) {
         var label = document.getElementById('pb-label-' + field);
         if (!label) {
            return;
         }

         var xInput = document.getElementById('inp-' + field + '-x');
         var yInput = document.getElementById('inp-' + field + '-y');
         if (!xInput || !yInput) {
            return;
         }

         var realX = parseInt(xInput.value, 10) || 0;
         var realY = parseInt(yInput.value, 10) || 0;
         var displayY = realY - label.offsetHeight;
         var displayX = realX <= 0
            ? Math.round((wrap.offsetWidth - label.offsetWidth) / 2)
            : realX;

         label.style.left = Math.max(0, displayX) + 'px';
         label.style.top = Math.max(0, displayY) + 'px';
      }

      function makeDraggable(field) {
         var label = document.getElementById('pb-label-' + field);
         if (!label || label.dataset.phonebgDragBound === '1') {
            return;
         }

         label.dataset.phonebgDragBound = '1';
         label.addEventListener('mousedown', function (event) {
            event.preventDefault();
            event.stopPropagation();

            var startMouseX = event.clientX;
            var startMouseY = event.clientY;
            var startLeft = label.offsetLeft;
            var startTop = label.offsetTop;
            label.style.cursor = 'grabbing';

            function onMove(moveEvent) {
               moveEvent.preventDefault();

               var newLeft = Math.max(
                  0,
                  Math.min(
                     wrap.offsetWidth - label.offsetWidth,
                     startLeft + moveEvent.clientX - startMouseX
                  )
               );
               var newTop = Math.max(
                  0,
                  Math.min(
                     wrap.offsetHeight - label.offsetHeight,
                     startTop + moveEvent.clientY - startMouseY
                  )
               );

               label.style.left = newLeft + 'px';
               label.style.top = newTop + 'px';

               var xInput = document.getElementById('inp-' + field + '-x');
               var yInput = document.getElementById('inp-' + field + '-y');
               if (xInput) {
                  xInput.value = Math.round(newLeft);
               }
               if (yInput) {
                  yInput.value = Math.round(newTop + label.offsetHeight);
               }
            }

            function onUp() {
               label.style.cursor = 'grab';
               document.removeEventListener('mousemove', onMove);
               document.removeEventListener('mouseup', onUp);
               markDirty();
            }

            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
         });
      }

      function initEditor() {
         ['name', 'mobile', 'label1', 'label2'].forEach(function (field) {
            placeLabel(field);
            makeDraggable(field);

            ['x', 'y'].forEach(function (axis) {
               var input = document.getElementById('inp-' + field + '-' + axis);
               if (input && input.dataset.phonebgBound !== '1') {
                  input.dataset.phonebgBound = '1';
                  input.addEventListener('input', function () {
                     placeLabel(field);
                  });
               }
            });

            var sizeInput = document.getElementById('inp-' + field + '-size');
            if (sizeInput && sizeInput.dataset.phonebgBound !== '1') {
               sizeInput.dataset.phonebgBound = '1';
               sizeInput.addEventListener('input', function () {
                  var label = document.getElementById('pb-label-' + field);
                  if (label) {
                     label.style.fontSize = Math.max(8, parseInt(sizeInput.value, 10) || 8) + 'px';
                  }
                  placeLabel(field);
               });
            }
         });

         ['label1', 'label2'].forEach(function (field) {
            var checkbox = document.getElementById('chk-' + field);
            var label = document.getElementById('pb-label-' + field);
            var input = document.getElementById('inp-' + field + '-text');

            if (checkbox && label && checkbox.dataset.phonebgBound !== '1') {
               checkbox.dataset.phonebgBound = '1';
               checkbox.addEventListener('change', function () {
                  label.style.display = checkbox.checked ? 'block' : 'none';
                  markDirty();
               });
            }

            if (input && label && input.dataset.phonebgBound !== '1') {
               input.dataset.phonebgBound = '1';
               input.addEventListener('input', function () {
                  label.textContent = input.value || input.placeholder;
                  placeLabel(field);
                  markDirty();
               });
            }
         });
      }

      function tryInit() {
         if (!image || !image.isConnected) {
            return;
         }

         if (image.complete && image.naturalWidth > 0 && image.naturalHeight > 0) {
            initEditor();
            return;
         }

         image.addEventListener('load', initEditor, { once: true });
      }

      if (form) {
         form.querySelectorAll('input, select').forEach(function (element) {
            element.addEventListener('input', markDirty);
            element.addEventListener('change', markDirty);
         });
         form.addEventListener('submit', function () {
            dirty = false;
         });
      }

      var templateTab = document.getElementById('tab-template-btn');
      if (templateTab) {
         templateTab.addEventListener('click', function (event) {
            if (dirty) {
               var message = templateTab.getAttribute('data-phonebg-confirm') || '';
               if (message && !window.confirm(message)) {
                  event.stopImmediatePropagation();
                  event.preventDefault();
               }
            }
         });
      }

      var colorText = document.getElementById('inp-font-color-text');
      var colorSwatch = document.getElementById('inp-font-color-swatch');
      if (colorText && colorSwatch) {
         colorSwatch.addEventListener('input', function () {
            colorText.value = colorSwatch.value;
            markDirty();
         });
         colorText.addEventListener('input', function () {
            if (/^#[0-9a-fA-F]{6}$/.test(colorText.value)) {
               colorSwatch.value = colorText.value;
            }
            markDirty();
         });
         colorText.addEventListener('change', function () {
            if (/^#[0-9a-fA-F]{6}$/.test(colorText.value)) {
               colorSwatch.value = colorText.value;
            }
         });
      }

      var positionsTab = document.getElementById('tab-positions-btn');
      if (positionsTab) {
         positionsTab.addEventListener('shown.bs.tab', function () {
            window.setTimeout(tryInit, 50);
         });
      }

      tryInit();
   }

   function initBasePreview() {
      var input = document.getElementById('inp-base-file');
      if (!input || input.dataset.phonebgBound === '1') {
         return;
      }

      input.dataset.phonebgBound = '1';
      input.addEventListener('change', function () {
         var wrap = document.getElementById('pb-new-preview-wrap');
         var preview = document.getElementById('pb-new-preview');
         if (!wrap || !preview) {
            return;
         }

         wrap.classList.add('d-none');
         preview.removeAttribute('src');

         if (!input.files[0]) {
            return;
         }

         if (input.files[0].type !== 'image/png') {
            window.alert('Invalid format. PNG only.');
            input.value = '';
            return;
         }

         if (input.files[0].size > 500 * 1024) {
            window.alert('File exceeds maximum allowed size (500 KB).');
            input.value = '';
            return;
         }

         var reader = new FileReader();
         reader.onload = function (event) {
            preview.src = event.target.result;
            wrap.classList.remove('d-none');
         };
         reader.readAsDataURL(input.files[0]);
      });
   }

   function init() {
      initPhonePreview();
      initConfigActions();
      initBasePreview();
   }

   if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', init, { once: true });
   } else {
      init();
   }
})();
