<style>
    .review-shopee { font-family: Arial, Helvetica, sans-serif; font-size: 14px; color: #222; background: #fff; }

    .review-skeleton { padding: 8px 0; }
    .review-skeleton .rs-item { display: flex; gap: 12px; padding: 16px 0; border-bottom: 1px solid #f0f0f0; }
    .review-skeleton .rs-avatar { flex: 0 0 40px; width: 40px; height: 40px; border-radius: 50%; background: #f0f0f0; }
    .review-skeleton .rs-body { flex: 1; display: flex; flex-direction: column; gap: 8px; }
    .review-skeleton .rs-line { height: 12px; background: linear-gradient(90deg, #f0f0f0 0%, #fafafa 50%, #f0f0f0 100%);
        background-size: 200% 100%; border-radius: 4px; animation: rs-pulse 1.4s infinite; }
    .review-skeleton .rs-line--name  { width: 120px; height: 14px; }
    .review-skeleton .rs-line--stars { width: 100px; height: 10px; }
    .review-skeleton .rs-line--text  { width: 100%; }
    .review-skeleton .rs-line--short { width: 70%; }
    @keyframes rs-pulse {
        0%   { background-position: 200% 0; }
        100% { background-position: -200% 0; }
    }
    #review-list-container.is-loading { opacity: 0.6; transition: opacity .15s; }

    /* Summary */
    .review-summary { display: grid; grid-template-columns: 240px 1fr; gap: 30px; padding: 24px;
        background: #fffbf8; border: 1px solid #f5e1d3; border-radius: 4px; margin-bottom: 20px; }
    .rs-overview { text-align: center; padding: 10px 0; }
    .rs-avg-number { font-size: 50px; color: #ee4d2d; font-weight: 500; line-height: 1; }
    .rs-avg-slash  { font-size: 22px; color: #ee4d2d; }
    .rs-overview__stars { font-size: 20px; margin: 6px 0; }

    .rating-fractional { position: relative; display: inline-block; line-height: 1; letter-spacing: 2px; }
    .rating-fractional .rf-bg { color: #d4d4d4; }
    .rating-fractional .rf-fg { position: absolute; top: 0; left: 0; width: var(--rating-pct, 0%); color: #ee4d2d; overflow: hidden; white-space: nowrap; }
    .rs-overview__count { font-size: 13px; color: #555; }

    .rs-distribution { display: flex; flex-direction: column; gap: 6px; }
    .rs-dist-row { display: grid; grid-template-columns: 50px 1fr 50px; align-items: center; gap: 10px;
        padding: 4px 6px; text-decoration: none; color: #222; border-radius: 4px; }
    .rs-dist-row:hover { background: #fff3eb; }
    .rs-dist-row.is-active { background: #fff5f0; }
    .rs-dist-label { font-size: 13px; }
    .rs-dist-label .fa-star { color: #ee4d2d; font-size: 11px; }
    .rs-dist-bar { height: 8px; background: #f0f0f0; border-radius: 999px; overflow: hidden; }
    .rs-dist-fill { display: block; height: 100%; background: #ee4d2d; border-radius: 999px; }
    .rs-dist-count { text-align: right; font-size: 13px; color: #757575; }

    .rs-criteria { grid-column: 1 / -1; padding-top: 16px; border-top: 1px dashed #f5e1d3; }
    .rs-criteria__title { font-weight: 600; margin-bottom: 10px; color: #555; }
    .rs-criteria__grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 14px; }
    .rs-crit-cell { padding: 8px; background: #fff; border: 1px solid #f0e0d2; border-radius: 4px; }
    .rs-crit-name { font-size: 13px; color: #757575; margin-bottom: 4px; }
    .rs-crit-stars { font-size: 13px; display: inline-flex; align-items: center; gap: 6px; }
    .rs-crit-fractional { font-size: 13px; letter-spacing: 1px; }
    .rs-crit-value { color: #ee4d2d; font-weight: 600; }

    /* Filter */
    .review-filter { background: #fafafa; border: 1px solid #ececec; border-radius: 4px;
        padding: 16px 18px; margin-bottom: 20px; }
    .rf-row { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 8px; align-items: center; }
    .rf-row--sort { justify-content: flex-end; margin-bottom: 0; }
    .rf-chip { display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px;
        border: 1px solid #d4d4d4; background: #fff; border-radius: 2px; font-size: 13px; color: #222;
        text-decoration: none; transition: all .15s; cursor: pointer; }
    .rf-chip:hover { border-color: #ee4d2d; color: #ee4d2d; }
    .rf-chip.is-active { border-color: #ee4d2d; color: #ee4d2d; background: #fff5f0; font-weight: 500; }
    .rf-count { color: #757575; font-size: 12px; }
    .rf-chip--tag { border-radius: 999px; }
    .rf-sort-label { margin: 0 8px 0 0; color: #757575; font-size: 13px; }
    .rf-sort-select { padding: 5px 10px; border: 1px solid #d4d4d4; background: #fff; border-radius: 2px;
        font-size: 13px; min-width: 160px; }

    /* Review item */
    .review-list { display: flex; flex-direction: column; }
    .review-item { padding: 20px 0; border-bottom: 1px solid #f0f0f0; }
    .review-item:last-child { border-bottom: none; }
    .ri-head { display: flex; gap: 12px; align-items: flex-start; }
    .ri-avatar { flex: 0 0 40px; width: 40px; height: 40px; border-radius: 50%; overflow: hidden;
        background: #ee4d2d; color: #fff; display: flex; align-items: center; justify-content: center;
        font-weight: 600; }
    .ri-avatar img { width: 100%; height: 100%; object-fit: cover; }
    .ri-meta { flex: 1; }
    .ri-name { font-weight: 500; font-size: 14px; }
    .ri-badge--verified { display: inline-block; margin-left: 8px; font-size: 11px; color: #26aa99; font-weight: 400; }
    .ri-stars { color: #d4d4d4; font-size: 11px; margin: 2px 0; }
    .ri-stars .fa-star.is-on { color: #ee4d2d; }
    .ri-sub { font-size: 12px; color: #757575; }
    .ri-dot { margin: 0 6px; color: #d4d4d4; }
    .ri-title { font-size: 14px; font-weight: 600; margin: 10px 0 4px; }
    .ri-text { margin: 8px 0; line-height: 1.55; white-space: pre-wrap; }

    .ri-criteria { margin: 8px 0; font-size: 13px; color: #555; }
    .ri-criteria summary { cursor: pointer; color: #2673dd; }
    .ri-criteria-list { list-style: none; padding: 8px 0 0 0; margin: 0; }
    .ri-criteria-list li { padding: 3px 0; color: #d4d4d4; }
    .ri-crit-name { color: #222; margin-right: 6px; }
    .ri-criteria-list .fa-star.is-on { color: #ee4d2d; }

    .ri-media { display: grid; grid-template-columns: repeat(auto-fill, minmax(80px, 100px)); gap: 6px;
        margin: 10px 0; }
    .ri-media-cell { position: relative; aspect-ratio: 1/1; overflow: hidden; border-radius: 2px;
        display: block; background: #f0f0f0; }
    .ri-media-cell img { width: 100%; height: 100%; object-fit: cover; transition: transform .2s; }
    .ri-media-cell:hover img { transform: scale(1.05); }
    .ri-media-cell--video::after { content: ""; position: absolute; inset: 0; background: rgba(0,0,0,.15); }
    .ri-media-play { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
        color: #fff; font-size: 24px; z-index: 1; text-shadow: 0 2px 6px rgba(0,0,0,.5); }
    .ri-media-dur { position: absolute; right: 4px; bottom: 4px; background: rgba(0,0,0,.65); color: #fff;
        font-size: 11px; padding: 1px 5px; border-radius: 2px; z-index: 2; }

    .ri-tags { display: flex; flex-wrap: wrap; gap: 6px; margin: 8px 0; }
    .ri-tag { font-size: 12px; padding: 3px 10px; background: #fff5f0; color: #ee4d2d; border-radius: 999px; }

    .ri-replies { margin: 12px 0 4px; padding: 12px 14px; background: #fafafa;
        border-left: 3px solid #ee4d2d; border-radius: 0 4px 4px 0; }
    .ri-reply + .ri-reply { margin-top: 10px; padding-top: 10px; border-top: 1px dashed #ececec; }
    .ri-reply-head { display: flex; align-items: center; gap: 10px; font-size: 13px; }
    .ri-reply-head strong { color: #ee4d2d; }
    .ri-reply--admin .ri-reply-head strong { color: #2673dd; }
    .ri-reply--user  .ri-reply-head strong { color: #222; }
    .ri-reply-date { color: #757575; font-size: 12px; }
    .ri-reply-text { margin-top: 4px; line-height: 1.5; }

    .ri-foot { display: flex; gap: 8px; margin-top: 12px; align-items: center; }
    .ri-btn { display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px;
        border: 1px solid #ececec; background: #fff; border-radius: 2px; font-size: 13px; color: #555;
        cursor: pointer; transition: all .15s; }
    .ri-btn:hover { border-color: #ee4d2d; color: #ee4d2d; }
    .ri-btn[aria-pressed="true"] { border-color: #ee4d2d; color: #ee4d2d; background: #fff5f0; }
    .ri-btn--report { margin-left: auto; color: #999; }
    .ri-count { color: #999; font-size: 12px; }

    .review-empty { text-align: center; padding: 60px 20px; color: #999; }
    .review-empty .fa { font-size: 40px; color: #d4d4d4; margin-bottom: 10px; }

    /* Write form */
    .review-write { margin-top: 30px; padding-top: 24px; border-top: 2px solid #ee4d2d; }
    .rw-locked { padding: 14px 18px; background: #e6f7f3; color: #26aa99; border-radius: 4px; }
    .rw-login-prompt { padding: 24px; text-align: center; background: #fafafa; border-radius: 4px; }
    .rw-login-prompt a { color: #ee4d2d; font-weight: 500; }
    .rw-summary { font-size: 16px; font-weight: 500; cursor: pointer; padding: 12px 0; list-style: none; }
    .rw-summary::-webkit-details-marker { display: none; }
    .rw-summary i { color: #ee4d2d; margin-right: 8px; }
    .rw-form { padding-top: 10px; }

    .rw-criteria { display: flex; flex-direction: column; gap: 12px; padding: 16px 20px;
        background: #fafafa; border-radius: 4px; margin-bottom: 16px; }
    .rw-crit-row { display: grid; grid-template-columns: 200px 1fr; align-items: center; gap: 16px; }
    .rw-crit-label { font-size: 14px; color: #222; margin: 0; }
    .rw-required { color: #ee4d2d; margin-left: 2px; }

    .rw-stars { display: inline-flex; flex-direction: row-reverse; gap: 4px; align-items: center; }
    .rw-stars input[type="radio"] { display: none; }
    .rw-stars label { font-size: 22px; color: #d4d4d4; cursor: pointer; transition: color .1s; margin: 0; }
    .rw-stars label:hover, .rw-stars label:hover ~ label,
    .rw-stars input:checked ~ label { color: #ee4d2d; }
    .rw-stars-hint { margin-left: 12px; font-size: 13px; color: #ee4d2d; }

    .rw-tags { padding: 12px 0; }
    .rw-section-label { font-weight: 500; color: #555; margin-bottom: 8px; display: block; }
    .rw-tag-grid { display: flex; flex-wrap: wrap; gap: 8px; }
    .rw-tag-chip input { display: none; }
    .rw-tag-chip span { display: inline-block; padding: 6px 14px; border: 1px solid #d4d4d4;
        border-radius: 999px; font-size: 13px; cursor: pointer; transition: all .15s; }
    .rw-tag-chip input:checked + span { border-color: #ee4d2d; background: #fff5f0; color: #ee4d2d; font-weight: 500; }

    .rw-field { margin: 14px 0; position: relative; }
    .rw-field > label { display: block; font-size: 13px; color: #555; margin-bottom: 6px; }
    .rw-counter { text-align: right; font-size: 12px; color: #999; margin-top: 4px; }

    .rw-media-drop { padding: 14px; background: #fafafa; border: 1px dashed #d4d4d4; border-radius: 4px; }
    .rw-media-trigger { display: inline-flex; align-items: center; gap: 8px; padding: 8px 16px;
        background: #fff; border: 1px solid #d4d4d4; border-radius: 2px; cursor: pointer; font-size: 13px; }
    .rw-media-trigger i { color: #ee4d2d; }
    .rw-media-preview { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
    .rw-media-preview .rwm-cell { position: relative; width: 80px; height: 80px; border-radius: 2px;
        overflow: hidden; background: #f0f0f0; }
    .rw-media-preview .rwm-cell img, .rw-media-preview .rwm-cell video {
        width: 100%; height: 100%; object-fit: cover; }
    .rw-media-preview .rwm-remove { position: absolute; top: 2px; right: 2px; width: 18px; height: 18px;
        line-height: 16px; text-align: center; background: rgba(0,0,0,.6); color: #fff; border-radius: 50%;
        cursor: pointer; font-size: 12px; }

    .rw-anon { padding: 10px 0; }
    .rw-checkbox { display: flex; align-items: center; gap: 8px; cursor: pointer; }

    .rw-actions { display: flex; gap: 10px; align-items: center; padding-top: 16px; border-top: 1px solid #f0f0f0; }
    .rw-submit { background: #ee4d2d; border-color: #ee4d2d; color: #fff; padding: 10px 32px;
        font-size: 14px; font-weight: 500; }
    .rw-submit:hover { background: #d73a17; }
    .rw-reset { color: #757575; }

    @media (max-width: 768px) {
        .review-summary { grid-template-columns: 1fr; gap: 18px; }
        .rs-overview { padding: 0; }
        .rw-crit-row { grid-template-columns: 1fr; gap: 4px; }
        .rs-criteria__grid { grid-template-columns: 1fr 1fr; }
    }
</style>
