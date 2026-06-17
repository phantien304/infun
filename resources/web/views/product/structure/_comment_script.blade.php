{{--
    Toàn bộ tương tác review = AJAX. KHÔNG append query string vào URL product.
    Endpoint/CSRF/productId đọc từ data-* trên #review-section.

    Behavior:
      - Filter chips (rating/has_media/has_text/tag) → toggle state local + reload list
      - Sort dropdown → reload list
      - Pagination link trong #review-list-container → AJAX (intercept click)
      - Save form submit → AJAX POST FormData (giữ file upload)
      - Report form submit → AJAX POST
      - Helpful/Unhelpful vote → AJAX POST (đã có pattern cũ)
      - Star hint + textarea counter + media preview client-side
--}}
<script>
(function () {
    'use strict';

    const section = document.getElementById('review-section');
    if (! section) return;

    // ============================== Config ==============================
    const LIST_URL   = section.dataset.listUrl;
    const SAVE_URL   = section.dataset.saveUrl;
    const VOTE_URL   = section.dataset.voteUrl;
    const REPORT_URL = section.dataset.reportUrl;
    const CSRF       = section.dataset.csrf;
    const HINT       = ['', 'Tệ', 'Không hài lòng', 'Bình thường', 'Hài lòng', 'Tuyệt vời'];

    // ============================ Filter state ==========================
    // Local in-memory state, KHÔNG ghi xuống URL/history.
    const state = {
        filter: { rating: null, has_media: 0, has_text: 0, tag: [] },
        sort:   '-review.helpful_count',
        page:   1,
    };

    function buildQuery() {
        const params = new URLSearchParams();
        if (state.filter.rating)     params.set('filter[rating]', state.filter.rating);
        if (state.filter.has_media)  params.set('filter[has_media]', 1);
        if (state.filter.has_text)   params.set('filter[has_text]', 1);
        (state.filter.tag || []).forEach(code => params.append('filter[tag][]', code));
        params.set('sort', state.sort);
        if (state.page > 1)          params.set('page', state.page);
        return params.toString();
    }

    function syncChipState() {
        // Toggle .is-active cho từng chip dựa trên state hiện tại.
        const noFilter = ! state.filter.rating && ! state.filter.has_media
            && ! state.filter.has_text && state.filter.tag.length === 0;

        section.querySelectorAll('[data-review-clear]').forEach(b => {
            b.classList.toggle('is-active', noFilter);
        });

        section.querySelectorAll('[data-review-filter]').forEach(btn => {
            const payload = JSON.parse(btn.getAttribute('data-review-filter') || '{}');
            let on = false;
            if ('rating' in payload)    on = +state.filter.rating === +payload.rating;
            else if ('has_media' in payload) on = !! state.filter.has_media;
            else if ('has_text' in payload)  on = !! state.filter.has_text;
            else if ('tag' in payload)       on = state.filter.tag.includes(payload.tag);
            btn.classList.toggle('is-active', on);
        });

        const sel = document.getElementById('reviewSortSelect');
        if (sel) sel.value = state.sort;
    }

    // ============================ AJAX list =============================
    let inflight = null;

    async function reload() {
        const container = document.getElementById('review-list-container');
        if (! container) return;
        if (inflight) inflight.abort();
        const ctrl = new AbortController();
        inflight = ctrl;

        container.classList.add('is-loading');
        try {
            const r = await fetch(LIST_URL + '?' + buildQuery(), {
                method: 'GET',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
                signal: ctrl.signal,
            });
            if (! r.ok) throw new Error('HTTP ' + r.status);
            container.innerHTML = await r.text();
        } catch (err) {
            if (err.name !== 'AbortError') console.warn('review list reload failed', err);
        } finally {
            container.classList.remove('is-loading');
            inflight = null;
        }
    }

    // =========================== Event handlers =========================
    // Delegated click on section — chip + pagination + vote + report-trigger.
    section.addEventListener('click', function (e) {
        const clearBtn = e.target.closest('[data-review-clear]');
        if (clearBtn) {
            e.preventDefault();
            state.filter = { rating: null, has_media: 0, has_text: 0, tag: [] };
            state.page = 1;
            syncChipState();
            reload();
            return;
        }

        const chip = e.target.closest('[data-review-filter]');
        if (chip) {
            e.preventDefault();
            const payload = JSON.parse(chip.getAttribute('data-review-filter') || '{}');
            if ('rating' in payload) {
                state.filter.rating = (+state.filter.rating === +payload.rating) ? null : +payload.rating;
            } else if ('has_media' in payload) {
                state.filter.has_media = state.filter.has_media ? 0 : 1;
            } else if ('has_text' in payload) {
                state.filter.has_text = state.filter.has_text ? 0 : 1;
            } else if ('tag' in payload) {
                const i = state.filter.tag.indexOf(payload.tag);
                if (i >= 0) state.filter.tag.splice(i, 1);
                else        state.filter.tag.push(payload.tag);
            }
            state.page = 1;
            syncChipState();
            reload();
            return;
        }

        // Intercept pagination link inside #review-list-container
        const pageLink = e.target.closest('#review-list-container .pagination a, #review-list-container .review-paging a');
        if (pageLink) {
            e.preventDefault();
            const url = new URL(pageLink.href, location.origin);
            const p = url.searchParams.get('page');
            state.page = p ? +p : 1;
            reload();
            // Scroll mềm về top section
            section.scrollIntoView({ behavior: 'smooth', block: 'start' });
            return;
        }

        // Report button — dispatch event để Alpine modal mở (thay
        // Bootstrap data-toggle="modal"). Alpine listener tự set
        // #reportReviewId value từ detail.reviewId.
        const reportBtn = e.target.closest('.ri-btn--report');
        if (reportBtn) {
            window.dispatchEvent(new CustomEvent('open-report-modal', {
                detail: { reviewId: reportBtn.dataset.reviewId }
            }));
            return;
        }

        // Helpful / Unhelpful vote
        const voteBtn = e.target.closest('.ri-btn--helpful, .ri-btn--unhelpful');
        if (voteBtn) {
            e.preventDefault();
            handleVote(voteBtn);
            return;
        }
    });

    // Sort dropdown
    document.addEventListener('change', function (e) {
        if (e.target && e.target.id === 'reviewSortSelect') {
            state.sort = e.target.value;
            state.page = 1;
            reload();
        }
    });

    // ============================ Vote ===================================
    function handleVote(btn) {
        const reviewId = btn.dataset.reviewId;
        const vote     = btn.dataset.vote;
        fetch(VOTE_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ review_id: reviewId, vote_type: vote }),
            credentials: 'same-origin',
        })
        .then(r => r.json())
        .then(data => {
            if (! data || ! data.success) {
                if (data && data.message) alert(data.message);
                return;
            }
            const article = btn.closest('.review-item');
            if (! article) return;
            if (data.helpful_count !== undefined) {
                article.querySelector('.ri-btn--helpful .ri-count').textContent = '(' + data.helpful_count + ')';
            }
            if (data.unhelpful_count !== undefined) {
                article.querySelector('.ri-btn--unhelpful .ri-count').textContent = '(' + data.unhelpful_count + ')';
            }
            article.querySelectorAll('.ri-btn--helpful, .ri-btn--unhelpful').forEach(b => {
                b.setAttribute('aria-pressed', String(b.dataset.vote === String(data.my_vote)));
            });
        })
        .catch(err => console.warn('vote failed', err));
    }

    // ====================== Save form (AJAX submit) =====================
    const saveForm = document.getElementById('reviewWriteForm');
    if (saveForm) {
        saveForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const fd = new FormData(saveForm);
            const submitBtn = saveForm.querySelector('.rw-submit');
            if (submitBtn) submitBtn.disabled = true;

            fetch(SAVE_URL, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': CSRF,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: fd,
                credentials: 'same-origin',
            })
            .then(r => r.json())
            .then(data => {
                if (submitBtn) submitBtn.disabled = false;
                if (data && data.success) {
                    alert(data.message || 'Đã gửi đánh giá.');
                    saveForm.reset();
                    const preview = document.getElementById('rwMediaPreview');
                    if (preview) preview.innerHTML = '';
                    state.page = 1;
                    reload();
                } else {
                    alert((data && data.message) ? data.message : 'Có lỗi xảy ra.');
                }
            })
            .catch(err => {
                if (submitBtn) submitBtn.disabled = false;
                console.warn('save review failed', err);
            });
        });
    }

    // ===================== Report form (AJAX submit) ====================
    const reportForm = document.getElementById('reviewReportForm');
    if (reportForm) {
        reportForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const fd = new FormData(reportForm);
            fetch(REPORT_URL, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': CSRF,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: fd,
                credentials: 'same-origin',
            })
            .then(r => r.json())
            .then(data => {
                alert((data && data.message) ? data.message : 'OK');
                if (data && data.success) {
                    reportForm.reset();
                    // Đóng modal Alpine — thay $(...).modal('hide') Bootstrap cũ.
                    window.dispatchEvent(new CustomEvent('close-report-modal'));
                }
            })
            .catch(err => console.warn('report failed', err));
        });
    }

    // ===================== Form UX helpers ==============================
    document.querySelectorAll('.rw-stars').forEach(function (group) {
        const hintEl = group.querySelector('.rw-stars-hint');
        group.querySelectorAll('input[type="radio"]').forEach(function (input) {
            input.addEventListener('change', function () {
                if (hintEl) hintEl.textContent = HINT[parseInt(input.value, 10)] || '';
            });
        });
    });

    const ta = document.getElementById('rw-text');
    const counter = document.querySelector('.rw-counter-now');
    if (ta && counter) {
        ta.addEventListener('input', function () { counter.textContent = ta.value.length; });
    }

    const mediaInput = document.getElementById('rwMediaInput');
    const mediaPreview = document.getElementById('rwMediaPreview');
    if (mediaInput && mediaPreview) {
        mediaInput.addEventListener('change', function () {
            mediaPreview.innerHTML = '';
            Array.from(mediaInput.files).forEach(function (file, idx) {
                const cell = document.createElement('div');
                cell.className = 'rwm-cell';
                const url = URL.createObjectURL(file);
                if (file.type.startsWith('video/')) {
                    cell.innerHTML = '<video src="' + url + '" muted></video>'
                        + '<span class="rwm-remove" data-idx="' + idx + '">&times;</span>';
                } else {
                    cell.innerHTML = '<img src="' + url + '" alt="">'
                        + '<span class="rwm-remove" data-idx="' + idx + '">&times;</span>';
                }
                mediaPreview.appendChild(cell);
            });
        });
        mediaPreview.addEventListener('click', function (e) {
            if (! e.target.classList.contains('rwm-remove')) return;
            mediaInput.value = '';
            mediaPreview.innerHTML = '';
        });
    }

    // ====== INITIAL LAZY LOAD (tối ưu A 2026-06-10) ======
    // Defer reload() tới khi user scroll gần tới #review-section (IntersectionObserver)
    // hoặc 1.5s sau page load nếu user không scroll. Tránh chặn LCP của trang detail.
    let didInitialLoad = false;
    function initialLoad() {
        if (didInitialLoad) return;
        didInitialLoad = true;
        reload();
    }

    if ('IntersectionObserver' in window) {
        const io = new IntersectionObserver(function (entries) {
            for (const e of entries) {
                if (e.isIntersecting) {
                    initialLoad();
                    io.disconnect();
                    break;
                }
            }
        }, { rootMargin: '300px 0px' });   // pre-fetch 300px trước khi section vào viewport
        io.observe(section);
    }
    // Fallback cho browser cũ + đảm bảo load nếu section nằm dưới fold mà user
    // không scroll (vd mở qua tab khác). 1.5s đủ để trang detail render xong LCP.
    setTimeout(initialLoad, 1500);
})();
</script>
