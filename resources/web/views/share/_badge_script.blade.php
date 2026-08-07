<style>
    [x-cloak] {
        display: none !important;
    }
</style>
<script>
    (function() {
        var badgeSeq = 0;

        function setBadges(sel, value) {
            document.querySelectorAll(sel).forEach(function(el) {
                el.textContent = value;
            });
        }

        function hydrateBadges() {
            var seq = ++badgeSeq;
            fetch('{{ route('cart.badge') }}', {
                    headers: {
                        'Accept': 'application/json'
                    },
                    credentials: 'same-origin',
                    cache: 'no-store'
                })
                .then(function(r) {
                    return r.ok ? r.json() : null;
                })
                .then(function(json) {
                    if (seq !== badgeSeq) return;
                    var d = (json && json.data) || null;
                    if (!d) return;
                    setBadges('[data-cart-badge]', d.cart);
                    setBadges('[data-wishlist-badge]', d.wishlist);
                })
                .catch(function() {});
        }


        function bindMutationSync() {
            if (!window.jQuery) return;
            window.jQuery(document).ajaxComplete(function(e, xhr, settings) {
                var url = (settings && settings.url) || '';
                if (/add-to-cart|checkout\/(add|update|remove|cart)|wish/i.test(url)) {
                    hydrateBadges();
                }
            });
        }

        function markActiveMenu() {
            var path = location.pathname.replace(/^\/+/, '').replace(/\/+$/, '');
            if (!path) return;
            document.querySelectorAll('.main-menu a[data-link], .mobile-menu-wrap a[data-link]')
                .forEach(function(a) {
                    if (a.getAttribute('data-link') === path) a.classList.add('active');
                });
        }

        var csrfMeta = document.querySelector('meta[name="csrf-token"]');
        var csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

        function applyToken(token) {
            if (!token) return;
            csrfToken = token;
            document.querySelectorAll('meta[name="csrf-token"]').forEach(function(m) {
                m.setAttribute('content', token);
            });
            document.querySelectorAll('input[name="_token"]').forEach(function(i) {
                i.value = token;
            });
        }

        function refreshCsrf() {
            fetch('{{ route('csrf.index') }}', {
                    headers: {
                        'Accept': 'application/json'
                    },
                    credentials: 'same-origin',
                    cache: 'no-store'
                })
                .then(function(r) {
                    return r.ok ? r.json() : null;
                })
                .then(function(json) {
                    applyToken(json && json.data);
                })
                .catch(function() {});
        }

        function bindCsrfRetry() {
            if (!window.jQuery) return;
            var $ = window.jQuery;

            $.ajaxPrefilter(function(options) {
                var method = (options.type || options.method || 'GET').toUpperCase();
                if (method !== 'GET' && method !== 'HEAD' && csrfToken) {
                    options.headers = options.headers || {};
                    options.headers['X-CSRF-TOKEN'] = csrfToken; // ghi đè token cũ handler set
                }
            });

            $(document).ajaxError(function(event, jqXHR, settings) {
                if (jqXHR.status !== 419) return;
                if (settings.headers && settings.headers['X-CSRF-Retry']) return; // đã retry 1 lần
                fetch('{{ route('csrf.index') }}', {
                        headers: {
                            'Accept': 'application/json'
                        },
                        credentials: 'same-origin',
                        cache: 'no-store'
                    })
                    .then(function(r) {
                        return r.ok ? r.json() : null;
                    })
                    .then(function(json) {
                        var token = json && json.data;
                        if (!token) return;
                        applyToken(token);
                        var retry = $.extend({}, settings);
                        retry.headers = $.extend({}, settings.headers, {
                            'X-CSRF-TOKEN': token,
                            'X-CSRF-Retry': '1'
                        });
                        if (typeof retry.data === 'string' && /(^|&)_token=/.test(retry.data)) {
                            retry.data = retry.data.replace(/(^|&)_token=[^&]*/, '$1_token=' +
                                encodeURIComponent(token));
                        } else if (retry.data && typeof retry.data === 'object' && '_token' in retry
                            .data) {
                            retry.data._token = token;
                        }
                        $.ajax(retry);
                    })
                    .catch(function() {});
            });
        }

        function init() {
            refreshCsrf();
            bindCsrfRetry();
            hydrateBadges();
            markActiveMenu();
            bindMutationSync();
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }
    })();
</script>
