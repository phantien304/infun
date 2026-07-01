/*
 * Variant availability / auto-resolve — logic tests (Node, no deps).
 * Chạy: node tests/js/variant-availability.test.js
 *
 * Trích 3 hàm THẬT từ public/web/js/style.js (không copy tay để tránh drift):
 *   isValueAvailable(selected, optionId, valueId)
 *   findVariant(selected)
 *   isSelectionFeasible(selected)
 * và mô phỏng đúng logic prune của recompute(keepOptionId).
 */
const fs = require('fs');
const path = require('path');

const STYLE = path.join(__dirname, '..', '..', 'public', 'web', 'js', 'style.js');
const src = fs.readFileSync(STYLE, 'utf8');

function extract(name) {
  const start = src.indexOf('function ' + name + '(');
  if (start < 0) throw new Error('not found: ' + name);
  let j = src.indexOf('{', start), depth = 0;
  for (; j < src.length; j++) {
    if (src[j] === '{') depth++;
    else if (src[j] === '}' && --depth === 0) { j++; break; }
  }
  return src.slice(start, j);
}

// Inject globals (variantMatrix, ALL_OOS) via a factory.
function engine(matrix, allOos = false) {
  const body = [extract('isValueAvailable'), extract('findVariant'), extract('isSelectionFeasible')].join('\n\n');
  const make = new Function('variantMatrix', 'ALL_OOS',
    body + '\nreturn {isValueAvailable, findVariant, isSelectionFeasible};');
  const api = make(matrix, allOos);

  // Mirror recompute(keepOptionId): nếu combo không feasible -> bỏ các option
  // khác, giữ option vừa click. Trả selection sau resolve.
  api.resolve = function (selected, keepOptionId) {
    const s = Object.assign({}, selected);
    if (!api.isSelectionFeasible(s)) {
      Object.keys(s).forEach(k => { if (parseInt(k, 10) !== keepOptionId) delete s[k]; });
    }
    return s;
  };
  return api;
}

let pass = 0, fail = 0;
const ok = (n, c) => { c ? pass++ : fail++; console.log((c ? 'ok   ' : 'FAIL ') + n); };

// option_id 10 = color, 20 = size
const V = (color, size, opts = {}) => ({
  id: opts.id, attributes: { "10": color, "20": size },
  subtract: opts.subtract !== undefined ? opts.subtract : true,
  available: opts.available !== undefined ? opts.available : 5,
});

/* ---------------- 1) DIAGONAL (sparse seed): 4 combos, each size↔1 color ------- */
(() => {
  console.log('\n# diagonal matrix (sparse seed default)');
  const e = engine([V(101,201,{id:1}), V(102,202,{id:2}), V(103,203,{id:3}), V(104,204,{id:4})]);
  ok('M -> color101 lit',            e.isValueAvailable({20:201},10,101) === true);
  ok('M -> color102 greyed',         e.isValueAvailable({20:201},10,102) === false);
  ok('M -> other sizes switchable',  e.isValueAvailable({20:201},20,202) === true);
  ok('color103 -> size M greyed',    e.isValueAvailable({10:103},20,201) === false);
  ok('color103 -> size S lit',       e.isValueAvailable({10:103},20,203) === true);
  ok('(M,color102) infeasible',      e.isSelectionFeasible({20:201,10:102}) === false);
  const r = e.resolve({20:201,10:102}, 10); // click color102 while M selected
  ok('resolve keeps color, drops size', JSON.stringify(r) === JSON.stringify({10:102}));
  ok('resolved feasible',            e.isSelectionFeasible(r) === true);
  ok('full (color102,XXL)->#2',      (e.findVariant({10:102,20:202})||{}).id === 2);
})();

/* ---------------- 2) FULL matrix (variants:seed --full): nothing greys -------- */
(() => {
  console.log('\n# full matrix (2 colors x 2 sizes, all exist)');
  const e = engine([V(101,201,{id:1}),V(101,202,{id:2}),V(102,201,{id:3}),V(102,202,{id:4})]);
  ok('M -> color101 lit', e.isValueAvailable({20:201},10,101) === true);
  ok('M -> color102 lit (no false OOS)', e.isValueAvailable({20:201},10,102) === true);
  ok('color102 -> both sizes lit', e.isValueAvailable({10:102},20,201) && e.isValueAvailable({10:102},20,202));
  ok('(color102,M) feasible', e.isSelectionFeasible({10:102,20:201}) === true);
})();

/* ---------------- 3) OUT-OF-STOCK single variant -------------------------------- */
(() => {
  console.log('\n# out-of-stock combo greys');
  const e = engine([V(101,201,{id:1,available:0}), V(101,202,{id:2,available:5}), V(102,202,{id:3,available:5})]);
  ok('(color101,M) OOS -> M greyed under color101', e.isValueAvailable({10:101},20,201) === false);
  ok('(color101,XXL) in stock -> lit',              e.isValueAvailable({10:101},20,202) === true);
})();

/* ---------------- 4) ALL_OOS data drift -> skip greying ------------------------- */
(() => {
  console.log('\n# ALL_OOS drift -> everything stays selectable');
  const m = [V(101,201,{id:1,available:0}), V(102,202,{id:2,available:0})];
  const e = engine(m, true); // ALL_OOS computed true in real IIFE
  ok('drift: value stays available', e.isValueAvailable({20:201},10,102) === true);
})();

/* ---------------- 5) THREE axes (color/size/material=30) ------------------------ */
(() => {
  console.log('\n# three-axis product');
  const T = (c,s,mat,id)=>({id,attributes:{"10":c,"20":s,"30":mat},subtract:true,available:5});
  const e = engine([T(101,201,301,1), T(101,201,302,2), T(102,202,301,3)]);
  ok('full (101,M,301) -> #1', (e.findVariant({10:101,20:201,30:301})||{}).id === 1);
  ok('(102,M,*) infeasible', e.isSelectionFeasible({10:102,20:201}) === false);
  const r = e.resolve({10:102,20:201,30:301}, 20); // click size M last
  ok('resolve -> feasible', e.isSelectionFeasible(r) === true);
  ok('resolve keeps clicked axis (size)', r[20] === 201);
})();

/* ---------------- 6) SINGLE-option product (size only) -------------------------- */
(() => {
  console.log('\n# single-option product');
  const S = (s,id)=>({id,attributes:{"20":s},subtract:true,available:5});
  const e = engine([S(201,1), S(202,2)]);
  ok('select M -> exact variant #1', (e.findVariant({20:201})||{}).id === 1);
  ok('both sizes selectable', e.isValueAvailable({},20,201) && e.isValueAvailable({},20,202));
})();

console.log(`\n==== ${pass} passed, ${fail} failed ====`);
process.exit(fail ? 1 : 0);
