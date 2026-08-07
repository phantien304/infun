@if (\App\Helpers\ThemeManager::isPreview())
    {{--
        Chế độ xem thử theme qua `?theme=`.

        Giữ tham số theme khi người xem bấm sang trang khác. Không có nó thì
        click đầu tiên là rơi về theme mặc định và bản demo gửi khách chỉ xem
        được đúng một trang.

        VÌ SAO LÀM Ở CLIENT chứ không ở url()/route():
        Rất nhiều URL trên trang không đi qua helper của Laravel — HTML dựng
        sẵn trong DB (config_footerN, html_custom của menu), link do JS sinh,
        link tuyệt đối trong nội dung bài viết. Vá ở tầng helper sẽ sót đúng
        những chỗ đó. Rewrite ở DOM thì bắt hết, và chỉ chạy ở chế độ xem thử
        nên không ảnh hưởng gì tới production (demo chốt cho khách gắn theme
        theo HOST, không dùng query).

        Chỉ đụng link CÙNG ORIGIN. Bỏ qua mailto:, tel:, #neo, target=_blank
        và link đã có sẵn ?theme=.
    --}}
    <script>
        (function() {
            var THEME = @json(\App\Helpers\ThemeManager::current());
            var origin = window.location.origin;

            function withTheme(href) {
                try {
                    var u = new URL(href, origin);
                    if (u.origin !== origin) return null;
                    if (u.searchParams.get('theme') === THEME) return null;
                    u.searchParams.set('theme', THEME);
                    return u.pathname + u.search + u.hash;
                } catch (e) {
                    return null;
                }
            }

            function patchLinks(root) {
                (root || document).querySelectorAll('a[href]').forEach(function(a) {
                    var raw = a.getAttribute('href');
                    if (!raw || raw.charAt(0) === '#') return;
                    if (/^(mailto:|tel:|javascript:|data:)/i.test(raw)) return;
                    var next = withTheme(a.href);
                    if (next) a.setAttribute('href', next);
                });
            }

            // Form GET: thêm hidden input, vì submit sẽ dựng lại query từ đầu
            // và nuốt mất theme trên action (đúng trường hợp ô tìm kiếm).
            function patchForms(root) {
                (root || document).querySelectorAll('form').forEach(function(f) {
                    var method = (f.getAttribute('method') || 'get').toLowerCase();
                    if (method !== 'get') {
                        var next = withTheme(f.action || window.location.href);
                        if (next) f.setAttribute('action', next);
                        return;
                    }
                    if (f.querySelector('input[name="theme"]')) return;
                    var i = document.createElement('input');
                    i.type = 'hidden';
                    i.name = 'theme';
                    i.value = THEME;
                    f.appendChild(i);
                });
            }

            function patchAll(root) {
                patchLinks(root);
                patchForms(root);
            }

            document.addEventListener('DOMContentLoaded', function() {
                patchAll(document);

                // Nội dung chèn sau khi tải (Alpine render, fetch, slider…)
                // cũng phải được vá, nếu không link trong mega menu hay lưới
                // sản phẩm động sẽ tuột mất theme.
                if (!window.MutationObserver) return;
                var pending = false;
                new MutationObserver(function() {
                    if (pending) return;
                    pending = true;
                    requestAnimationFrame(function() {
                        pending = false;
                        patchAll(document);
                    });
                }).observe(document.body, {
                    childList: true,
                    subtree: true
                });
            });
        })();
    </script>
@endif
