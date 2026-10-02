/**
 * 管理画面オンボーディングツアー（V2）
 *
 * 仕様の全文は docs/tour-steps.md を参照。
 * - 4章構成。章単位の単独起動・スキップ・中断再開に対応
 * - tier（required / recommended / optional）で入力の強制度を分ける
 * - ブロックエディタのiframe（editor-canvas）内の要素もハイライトできる
 * - 前提条件が欠けているステップは「黙って飛ばさず」理由を説明して進む
 * - サンプル値ボタンはDOMに値を入れるだけ（DBへの保存はユーザーの保存操作に委ねる）
 */
(function () {
	'use strict';

	if (typeof window.lpServiceTourData === 'undefined') {
		return;
	}

	var DATA = window.lpServiceTourData;
	var STORAGE_KEY = 'lpServiceTourState';
	var ALL_STEPS = DATA.steps || [];
	var PREREQ = DATA.prerequisites || {};
	var currentScreenId = DATA.screenId || '';

	var scrimEl = null;
	var spotlightEl = null;
	var tooltipEl = null;
	var cardEl = null;
	var pillEl = null;
	var toastEl = null;
	var toastTimer = null;
	var gateTimer = null;
	var undoSnapshot = null;
	var trackedHit = null;
	var trackHandler = null;
	var publishWatcher = null;
	var saveWatcher = null;
	var panelWatcher = null;
	var fullscreenHandled = false;

	// ---------- state ----------
	function getState() {
		try {
			var raw = window.localStorage.getItem(STORAGE_KEY);
			var parsed = raw ? JSON.parse(raw) : null;
			return parsed && typeof parsed === 'object' ? parsed : null;
		} catch (e) {
			return null;
		}
	}

	function setState(patch) {
		var state = getState() || { active: true, productIds: [], introShown: {} };
		Object.keys(patch).forEach(function (key) {
			state[key] = patch[key];
		});
		try {
			window.localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
		} catch (e) { /* プライベートブラウジング等は無視 */ }
		return state;
	}

	function clearState() {
		try {
			window.localStorage.removeItem(STORAGE_KEY);
		} catch (e) { /* noop */ }
	}

	// ---------- step scope ----------

	/**
	 * デザインテンプレート。LP編集画面ではPHPが渡した実値、それ以外の画面では
	 * 章1で記録した値を使う。どちらも無ければ standard とみなして番号を数える。
	 * シンプル・ポップ等の「simple-*」は見た目違いでブロック構成が同じため、ツアー上は simple として扱う。
	 */
	function activeDesign() {
		var state = getState();
		var design = PREREQ.designTemplate || (state && state.design) || 'standard';
		return design.indexOf('simple-') === 0 ? 'simple' : design;
	}

	/**
	 * 現在の進行スコープに含まれるステップ一覧。
	 * デザインで出し分け、章の単独起動時はその章だけ・移動ステップ抜きにする。
	 */
	function scopedSteps() {
		var state = getState() || {};
		var design = activeDesign();
		var only = state.chapterOnly || null;

		return ALL_STEPS.filter(function (step) {
			if (step.designs && step.designs.indexOf(design) === -1) return false;
			if (only) {
				if (step.chapter !== only) return false;
				if (step.transition) return false;
			}
			return true;
		});
	}

	function indexOfId(id, steps) {
		var list = steps || scopedSteps();
		for (var i = 0; i < list.length; i++) {
			if (list[i].id === id) return i;
		}
		return -1;
	}

	function stepById(id) {
		var list = scopedSteps();
		var idx = indexOfId(id, list);
		return idx === -1 ? null : list[idx];
	}

	function chapterSteps(chapter) {
		return scopedSteps().filter(function (step) {
			return step.chapter === chapter;
		});
	}

	function chapterMeta(chapter) {
		return (DATA.chapters && DATA.chapters[chapter]) || { number: chapter, title: '', intro: '' };
	}

	/**
	 * 章内の通し番号。デザインによって表示されるステップが変わるため、
	 * タイトルに番号を直書きせず、実際の位置から振る。
	 */
	function stepNumber(position) {
		if (position >= 1 && position <= 20) {
			return String.fromCharCode(0x2460 + position - 1);
		}
		return position + '.';
	}

	function screenLabel(screenId) {
		return (DATA.screenLabels && DATA.screenLabels[screenId]) || screenId;
	}

	// ---------- prerequisites ----------
	function unmetRequirement(step) {
		if (!step.requires || !step.requires.length) return null;
		for (var i = 0; i < step.requires.length; i++) {
			if (!PREREQ[step.requires[i]]) return step.requires[i];
		}
		return null;
	}

	function bodyFor(step) {
		return unmetRequirement(step) && step.emptyBody ? step.emptyBody : step.body;
	}

	// ---------- canvas (iframe) ----------
	function canvasFrame() {
		var frame = document.querySelector('iframe[name="editor-canvas"]');
		if (!frame) return null;
		try {
			return frame.contentDocument ? frame : null;
		} catch (e) {
			return null;
		}
	}

	function searchRoots(step) {
		var roots = [];
		if (step.frame === 'canvas') {
			var frame = canvasFrame();
			if (frame) roots.push({ doc: frame.contentDocument, frame: frame });
		}
		roots.push({ doc: document, frame: null });
		return roots;
	}

	/**
	 * ターゲット要素を解決する。step.target のセレクターを先頭から順に試し、
	 * canvas 指定ならiframe内 → 親ドキュメントの順に探す。
	 */
	function resolveTarget(step) {
		var selectors = step.target || [];

		// ⑤ロゴのように、他の入力値によって対象が変わるステップ
		if (step.targetImage) {
			var picked = document.querySelector('input[name="lp_header_logo_type"]:checked');
			if (picked && picked.value === 'image') {
				selectors = step.targetImage;
			}
		}

		var roots = searchRoots(step);
		var fallback = null;

		for (var r = 0; r < roots.length; r++) {
			for (var s = 0; s < selectors.length; s++) {
				var found;
				if (step.targetNth) {
					var all = roots[r].doc.querySelectorAll(selectors[s]);
					found = all.length >= step.targetNth ? all[step.targetNth - 1] : null;
				} else {
					found = roots[r].doc.querySelector(selectors[s]);
				}
				if (!found) continue;

				var hit = buildHit(found, roots[r], step);

				// セレクター候補には、その画面では非表示の要素（管理バーの「表示」リンク等）が
				// 混ざる。同じ候補リスト内に見えている要素があればそちらを優先する。
				var rect = found.getBoundingClientRect();
				if (rect.width > 0 || rect.height > 0) return hit;
				if (!fallback) fallback = hit;
			}
		}

		return fallback;
	}

	function buildHit(el, root, step) {
		var hit = { el: el, frame: root.frame };
		if (step.targetUnion) {
			hit.union = step.targetUnion
				.map(function (sel) { return root.doc.querySelector(sel); })
				.filter(Boolean);
		}
		return hit;
	}

	/**
	 * 複数フィールドを1つの枠で囲むための合成矩形。
	 * （セレクト2の「ラベル＋選択肢」、チェックボックス1〜4 など）
	 */
	function unionRect(elements) {
		var top = Infinity, left = Infinity, bottom = -Infinity, right = -Infinity;

		elements.forEach(function (el) {
			var r = el.getBoundingClientRect();
			if (r.width === 0 && r.height === 0) return;
			top = Math.min(top, r.top);
			left = Math.min(left, r.left);
			bottom = Math.max(bottom, r.bottom);
			right = Math.max(right, r.right);
		});

		if (top === Infinity) return null;

		return { top: top, left: left, bottom: bottom, right: right, width: right - left, height: bottom - top };
	}

	/**
	 * ハイライト矩形。iframe内の要素はiframe自身のオフセットを足して
	 * 親ドキュメントのビューポート座標に変換する。
	 */
	function rectOf(hit) {
		var el = hit.expanded || hit.el;
		var rect = (hit.union && hit.union.length ? unionRect(hit.union) : null) || el.getBoundingClientRect();

		if (!hit.frame) return rect;

		var fr = hit.frame.getBoundingClientRect();
		return {
			top: rect.top + fr.top,
			left: rect.left + fr.left,
			width: rect.width,
			height: rect.height,
			bottom: rect.bottom + fr.top,
			right: rect.right + fr.left
		};
	}

	// ---------- meta box helpers ----------
	function openPostboxIfClosed(el) {
		var postbox = el && el.closest ? el.closest('.postbox') : null;
		if (postbox && postbox.classList.contains('closed')) {
			postbox.classList.remove('closed');
			var toggleBtn = postbox.querySelector('.handlediv');
			if (toggleBtn) toggleBtn.setAttribute('aria-expanded', 'true');
		}
	}

	/**
	 * ブロックエディタは normal/advanced コンテキストのメタボックスを、本文下の
	 * 「メタボックス」パネルに折りたたんで格納する。閉じたままだと対象要素の
	 * 高さが0になり測位できないため、先に開いておく。
	 */
	function ensureMetaBoxesPanelOpen() {
		var toggle = document.querySelector('.edit-post-meta-boxes-main__presenter button[aria-expanded]');
		if (!toggle) {
			toggle = Array.prototype.filter.call(document.querySelectorAll('button'), function (b) {
				var label = (b.textContent || '').trim();
				return label === 'メタボックス' || label === 'Meta Boxes';
			})[0];
		}
		if (toggle && toggle.getAttribute('aria-expanded') === 'false') {
			toggle.click();
			return true;
		}
		return false;
	}

	/**
	 * ブロック設定（インスペクター）は「サイドバーが開いている」だけでは中身が出ず、
	 * 対象のブロックが選択されている必要がある。ツアーでは両方を揃えてから案内する。
	 */
	function selectBlockByName(name, attemptsLeft) {
		try {
			var blocks = wp.data.select('core/block-editor').getBlocks();
			for (var i = 0; i < blocks.length; i++) {
				if (blocks[i].name === name) {
					wp.data.dispatch('core/block-editor').selectBlock(blocks[i].clientId);
					return;
				}
			}
		} catch (e) { /* 下のリトライへ */ }

		// ページ読み込み直後はブロックがまだストアに載っていないことがある
		if (attemptsLeft > 0) {
			setTimeout(function () {
				selectBlockByName(name, attemptsLeft - 1);
			}, 300);
		}
	}

	function ensureInspectorOpen() {
		var opened = false;

		// WP 6.5 以降はこちらが実体。core/edit-post 側は後方互換のため両方呼ぶ。
		try {
			wp.data.dispatch('core/interface').enableComplementaryArea('core/edit-post', 'edit-post/block');
			opened = true;
		} catch (e) { /* noop */ }

		try {
			wp.data.dispatch('core/edit-post').openGeneralSidebar('edit-post/block');
			opened = true;
		} catch (e) { /* noop */ }

		if (opened) return;

		var btn = document.querySelector('.editor-header__settings [aria-label*="設定"], .edit-post-header__settings [aria-label*="設定"]');
		if (btn && btn.getAttribute('aria-pressed') === 'false') btn.click();
	}

	// ---------- editor title ----------
	function isTitleStep(step) {
		return step.id === 'c1-title' || step.id === 'c2-title';
	}

	function editorTitle() {
		try {
			return wp.data.select('core/editor').getEditedPostAttribute('title') || '';
		} catch (e) {
			return null;
		}
	}

	function setEditorTitle(value) {
		try {
			wp.data.dispatch('core/editor').editPost({ title: value });
			return true;
		} catch (e) {
			return false;
		}
	}

	// ---------- 保存 / 公開の状態 ----------
	var PUBLISHED_STATUSES = ['publish', 'future', 'private'];

	function editorStore() {
		try {
			return wp.data.select('core/editor');
		} catch (e) {
			return null;
		}
	}

	/**
	 * 投稿が実際に公開済みか（true / false / 判定不能なら null）。
	 *
	 * Gutenberg の「公開」ボタンは1回目で確認パネルを開くだけで、公開は確定しない。
	 * クリックを合図にすると「まだ公開していないのに完了扱い」になるため、
	 * ここでストアの状態そのものを見る。
	 */
	function isPostPublished() {
		var store = editorStore();
		if (!store || !store.getCurrentPost) return null;

		var post = store.getCurrentPost();
		if (!post || !post.status) return null;
		if (PUBLISHED_STATUSES.indexOf(post.status) === -1) return false;

		return !store.isSavingPost();
	}

	/**
	 * 未保存の変更が無い状態か（下書き保存・更新の完了判定用）。
	 * クラシックメタボックスの変更はストアが追跡しないため、そこまでは見ない。
	 */
	function isPostSaved() {
		var store = editorStore();
		if (!store || !store.getCurrentPost) return null;

		var post = store.getCurrentPost();
		if (!post) return null;
		if (post.status === 'auto-draft') return false;
		if (store.isSavingPost()) return false;

		return !store.isEditedPostDirty();
	}

	/**
	 * ブロックエディタのフルスクリーンモードでは左メニューも管理バーも消える。
	 * ツアーの案内対象が丸ごと見えなくなり操作不能になるので解除しておく。
	 */
	function exitFullscreenMode() {
		if (fullscreenHandled) return;
		if (!document.body.classList.contains('is-fullscreen-mode')) return;
		fullscreenHandled = true;

		// WP 6.5 以降は core/preferences。名前空間がバージョンで違うため両方試す。
		try {
			var pref = wp.data.select('core/preferences');
			if (pref) {
				if (pref.get('core', 'fullscreenMode')) {
					wp.data.dispatch('core/preferences').set('core', 'fullscreenMode', false);
					return;
				}
				if (pref.get('core/edit-post', 'fullscreenMode')) {
					wp.data.dispatch('core/preferences').set('core/edit-post', 'fullscreenMode', false);
					return;
				}
			}
		} catch (e) { /* 下のフォールバックへ */ }

		try {
			wp.data.dispatch('core/edit-post').toggleFeature('fullscreenMode');
		} catch (e) { /* noop */ }
	}

	// ---------- tier / gating ----------
	function isFormControl(el) {
		return el && /^(INPUT|SELECT|TEXTAREA)$/.test(el.tagName);
	}

	/**
	 * このステップが「入力済みか」を判定するための値。
	 * 判定できないステップ（ボタン・ブロックなど）は null を返し、ゲートを掛けない。
	 */
	function stepValue(step, hit) {
		if (isTitleStep(step)) return editorTitle();
		if (!hit || !isFormControl(hit.el)) return null;
		if (hit.el.type === 'radio' || hit.el.type === 'checkbox') return null;
		return hit.el.value;
	}

	function isBlocked(step, hit) {
		if (unmetRequirement(step)) return false;

		// 公開・保存を待つステップは、実際にその状態になるまで「次へ」を無効にする
		if (step.awaitPublish) return isPostPublished() === false;
		if (step.awaitSave) return isPostSaved() === false;

		if (step.tier !== 'required') return false;
		var value = stepValue(step, hit);
		return value !== null && String(value).trim() === '';
	}

	function tierBadge(step, hit) {
		if (unmetRequirement(step)) return '';

		if (step.awaitPublish) {
			var published = isPostPublished();
			if (published === false) return '<span class="lpt-badge lpt-badge-req">まだ公開されていません</span>';
			if (published === true) return '<span class="lpt-badge lpt-badge-done">公開済み</span>';
			return '';
		}

		if (step.awaitSave) {
			return isPostSaved() === false ? '<span class="lpt-badge lpt-badge-req">未保存</span>' : '';
		}

		var value = stepValue(step, hit);
		if (value === null || String(value).trim() !== '') return '';
		if (step.tier === 'required') return '<span class="lpt-badge lpt-badge-req">入力が必要です</span>';
		if (step.tier === 'recommended') return '<span class="lpt-badge lpt-badge-rec">未入力</span>';
		return '';
	}

	// ---------- sample values ----------
	function sampleSet(key) {
		return (DATA.samples && DATA.samples[key]) || null;
	}

	function readSampleField(field) {
		if (field.type === 'editorTitle') return editorTitle();
		var el = document.querySelector(field.selector);
		return el ? el.value : null;
	}

	function writeSampleField(field, value) {
		if (field.type === 'editorTitle') return setEditorTitle(value);

		var el = document.querySelector(field.selector);
		if (!el) return false;

		el.value = value;
		// プログラムからの代入ではブロックエディタが「未保存の変更あり」を検知しないため、
		// 明示的にイベントを発火させる。
		el.dispatchEvent(new Event('input', { bubbles: true }));
		el.dispatchEvent(new Event('change', { bubbles: true }));
		return true;
	}

	function filledSampleFields(set) {
		return set.fields.filter(function (field) {
			var current = readSampleField(field);
			return current !== null && String(current).trim() !== '';
		});
	}

	function applySample(key, mode) {
		var set = sampleSet(key);
		if (!set) return;

		undoSnapshot = { key: key, values: [] };

		set.fields.forEach(function (field) {
			var current = readSampleField(field);
			if (current === null) return;

			var isEmpty = String(current).trim() === '';
			if (mode === 'empty' && !isEmpty) return;

			undoSnapshot.values.push({ field: field, previous: current });
			writeSampleField(field, field.value);
		});
	}

	function undoSample() {
		if (!undoSnapshot) return;
		undoSnapshot.values.forEach(function (entry) {
			writeSampleField(entry.field, entry.previous);
		});
		undoSnapshot = null;
	}

	// ---------- overlay primitives ----------
	function ensureOverlay() {
		if (!scrimEl) {
			scrimEl = document.createElement('div');
			scrimEl.className = 'lpt-scrim';
			document.body.appendChild(scrimEl);
		}
		if (!spotlightEl) {
			spotlightEl = document.createElement('div');
			spotlightEl.className = 'lpt-spotlight';
			document.body.appendChild(spotlightEl);
		}
		if (!tooltipEl) {
			tooltipEl = document.createElement('div');
			tooltipEl.className = 'lpt-tooltip';
			document.body.appendChild(tooltipEl);
		}
	}

	/**
	 * ウィンドウのリサイズやスクロールで対象要素は動く（管理画面は幅によって
	 * ボタンが折り返す）。表示中はその都度測り直さないとスポットライトがずれる。
	 */
	function startTracking(hit) {
		stopTracking();
		trackedHit = hit;
		trackHandler = function () {
			if (trackedHit) positionAround(trackedHit);
		};
		window.addEventListener('resize', trackHandler);
		window.addEventListener('scroll', trackHandler, true);

		var frame = hit.frame;
		if (frame && frame.contentWindow) {
			try {
				frame.contentWindow.addEventListener('scroll', trackHandler, true);
			} catch (e) { /* noop */ }
		}
	}

	function stopTracking() {
		if (trackHandler) {
			window.removeEventListener('resize', trackHandler);
			window.removeEventListener('scroll', trackHandler, true);
			if (trackedHit && trackedHit.frame && trackedHit.frame.contentWindow) {
				try {
					trackedHit.frame.contentWindow.removeEventListener('scroll', trackHandler, true);
				} catch (e) { /* noop */ }
			}
		}
		trackHandler = null;
		trackedHit = null;
	}

	function stopPublishWatch() {
		clearInterval(publishWatcher);
		publishWatcher = null;
	}

	function stopSaveWatch() {
		clearInterval(saveWatcher);
		saveWatcher = null;
	}

	function stopPanelWatch() {
		clearInterval(panelWatcher);
		panelWatcher = null;
	}

	function teardownOverlay() {
		clearInterval(gateTimer);
		gateTimer = null;
		stopPublishWatch();
		stopSaveWatch();
		stopPanelWatch();
		stopTracking();
		[scrimEl, spotlightEl, tooltipEl, cardEl].forEach(function (el) {
			if (el && el.parentNode) el.parentNode.removeChild(el);
		});
		scrimEl = spotlightEl = tooltipEl = cardEl = null;
	}

	function removePill() {
		if (pillEl && pillEl.parentNode) pillEl.parentNode.removeChild(pillEl);
		pillEl = null;
	}

	function showToast(message) {
		if (!toastEl) {
			toastEl = document.createElement('div');
			toastEl.className = 'lpt-toast';
			document.body.appendChild(toastEl);
		}
		toastEl.textContent = message;
		requestAnimationFrame(function () {
			toastEl.classList.add('show');
		});
		clearTimeout(toastTimer);
		toastTimer = setTimeout(function () {
			if (toastEl) toastEl.classList.remove('show');
		}, 3600);
	}

	function positionAround(hit) {
		if (!spotlightEl || !tooltipEl) return;

		var rect = rectOf(hit);
		var pad = 8;

		spotlightEl.style.top = (rect.top - pad) + 'px';
		spotlightEl.style.left = (rect.left - pad) + 'px';
		spotlightEl.style.width = (rect.width + pad * 2) + 'px';
		spotlightEl.style.height = (rect.height + pad * 2) + 'px';

		var tooltipWidth = 340;
		var margin = 16;
		var left = Math.min(Math.max(rect.left, margin), window.innerWidth - tooltipWidth - margin);
		if (left < margin) left = margin;

		var height = tooltipEl.offsetHeight || 240;
		var top;
		if (rect.bottom + height + 20 < window.innerHeight) {
			top = rect.bottom + 16;
		} else if (rect.top - height - 20 > 0) {
			top = rect.top - height - 16;
		} else {
			top = Math.max(margin, (window.innerHeight - height) / 2);
		}

		tooltipEl.style.left = left + 'px';
		tooltipEl.style.top = top + 'px';
	}

	// ---------- retry helpers ----------
	function findTargetWithRetry(step, attemptsLeft, callback) {
		var hit = resolveTarget(step);
		if (hit || attemptsLeft <= 0) {
			callback(hit);
			return;
		}
		if (step.frame !== 'canvas') ensureMetaBoxesPanelOpen();
		setTimeout(function () {
			findTargetWithRetry(step, attemptsLeft - 1, callback);
		}, 200);
	}

	function waitUntilVisible(hit, attemptsLeft, callback) {
		var rect = rectOf(hit);
		if (rect.width > 0 || rect.height > 0 || attemptsLeft <= 0) {
			callback();
			return;
		}
		ensureMetaBoxesPanelOpen();
		openPostboxIfClosed(hit.el);
		setTimeout(function () {
			waitUntilVisible(hit, attemptsLeft - 1, callback);
		}, 200);
	}

	// ---------- cards（章頭・章末・お知らせ） ----------
	function showCard(html, actions) {
		teardownOverlay();
		removePill();

		scrimEl = document.createElement('div');
		scrimEl.className = 'lpt-scrim lpt-scrim-solid';
		document.body.appendChild(scrimEl);

		cardEl = document.createElement('div');
		cardEl.className = 'lpt-card';
		cardEl.innerHTML = html +
			'<div class="lpt-card-actions">' +
			actions.map(function (action, i) {
				return '<button type="button" class="lpt-btn ' + (action.primary ? 'lpt-btn-primary' : '') + '" data-idx="' + i + '">' + action.label + '</button>';
			}).join('') +
			'</div>';
		document.body.appendChild(cardEl);

		actions.forEach(function (action, i) {
			cardEl.querySelector('[data-idx="' + i + '"]').addEventListener('click', action.onClick);
		});
	}

	/**
	 * 章の入口診断。前の章の入力が欠けていて、この章で空欄になる箇所があれば警告する。
	 */
	function chapterWarnings(chapter) {
		var notes = [];

		// serviceContextKnown が false の画面（一覧画面など）は「どのLPの話か」が
		// 分からないだけで、未設定と決まったわけではない。誤った警告を出さない。
		if (chapter === 2 && PREREQ.serviceContextKnown && !PREREQ.filterConfigured) {
			notes.push('このLPの「絞り込みフォーム設定」が未入力です。このまま進むと商品側の「絞り込み条件」欄は空欄のままになります（仕組みの説明だけ行います）。');
		}
		if (chapter === 4 && PREREQ.serviceContextKnown && !PREREQ.hasProducts) {
			notes.push('このLPに紐づく公開済みの商品がありません。比較テーブル・ランキングで選べる商品が出てきません。');
		}
		if (chapter === 4 && !PREREQ.hasOperatorInfo) {
			notes.push('公開済みの運営者情報がありません。「運営者情報をリンクする」で選べる選択肢が出てきません。');
		}

		if (!notes.length) return '';

		return '<div class="lpt-warn"><strong>確認</strong>' +
			notes.map(function (note) { return '<p>' + note + '</p>'; }).join('') +
			'</div>';
	}

	function showChapterIntro(chapter, onStart) {
		var meta = chapterMeta(chapter);
		var total = chapterSteps(chapter).length;

		showCard(
			'<p class="lpt-card-eyebrow">章' + meta.number + ' / 4</p>' +
			'<h2 class="lpt-card-title">' + meta.title + '</h2>' +
			'<p class="lpt-card-body">' + meta.intro + '</p>' +
			'<p class="lpt-card-meta">このあと ' + total + ' ステップです</p>' +
			chapterWarnings(chapter),
			[
				{ label: 'ガイドを終了', onClick: endTour },
				{ label: '始める', primary: true, onClick: onStart }
			]
		);
	}

	function showChapterOutro(chapter, nextStep) {
		var outroSet = (DATA.outros && DATA.outros[chapter]) || null;
		var outro = outroSet ? (outroSet[activeDesign()] || outroSet['*']) : null;

		if (!outro) {
			proceedTo(nextStep);
			return;
		}

		var state = getState() || {};
		var count = (state.productIds || []).length;

		var html =
			'<p class="lpt-card-eyebrow">章' + chapter + ' 完了</p>' +
			'<h2 class="lpt-card-title">' + outro.done + (chapter === 2 && count ? '（現在 ' + count + ' 件）' : '') + '</h2>' +
			'<p class="lpt-card-body"><strong>次の予告:</strong> ' + outro.next + '</p>' +
			(outro.note ? '<p class="lpt-card-note">' + outro.note + '</p>' : '');

		var actions = [];

		if (outro.repeat) {
			actions.push({
				label: 'もう1件作る',
				onClick: function () {
					// 章2を③（商品名）から再走する。移動用の①②は飛ばす。
					setState({ currentId: 'c2-title', repeating: true });
					window.location.href = DATA.urls.newProduct;
				}
			});
		}

		if (nextStep) {
			actions.push({
				label: '章' + nextStep.chapter + 'へ進む',
				primary: true,
				onClick: function () { jumpToChapter(nextStep.chapter); }
			});
		} else {
			actions.push({ label: '完了', primary: true, onClick: completeTour });
		}

		showCard(html, actions);
	}

	function showNotFoundCard(step) {
		showCard(
			'<p class="lpt-card-eyebrow">' + step.title + '</p>' +
			'<h2 class="lpt-card-title">この画面に該当する項目が見つかりませんでした</h2>' +
			'<p class="lpt-card-body">' + bodyFor(step) + '</p>' +
			'<p class="lpt-card-note">画面の状態によっては表示されない項目です。ガイドはこのまま次へ進みます。</p>',
			[
				{ label: 'ガイドを終了', onClick: endTour },
				{ label: '次へ', primary: true, onClick: function () { advance(step.id); } }
			]
		);
	}

	// ---------- step rendering ----------
	function renderStep(step) {
		exitFullscreenMode();

		var state = getState() || {};
		var introShown = state.introShown || {};
		var firstOfChapter = chapterSteps(step.chapter)[0];

		if (firstOfChapter && firstOfChapter.id === step.id && !introShown[step.chapter] && !state.repeating) {
			introShown[step.chapter] = true;
			setState({ introShown: introShown });
			showChapterIntro(step.chapter, function () { renderStep(step); });
			return;
		}

		if (state.repeating) setState({ repeating: false });

		if (step.selectBlock) selectBlockByName(step.selectBlock, 15);
		if (step.openInspector) ensureInspectorOpen();

		findTargetWithRetry(step, 20, function (hit) {
			if (!hit) {
				showNotFoundCard(step);
				return;
			}
			if (step.expandTo && hit.el.closest) {
				hit.expanded = hit.el.closest(step.expandTo) || null;
			}
			showStepFor(step, hit);
		});
	}

	function showStepFor(step, hit) {
		teardownOverlay();
		removePill();
		ensureOverlay();

		var list = scopedSteps();
		var inChapter = chapterSteps(step.chapter);
		var position = indexOfId(step.id, inChapter) + 1;
		var overall = indexOfId(step.id, list) + 1;
		var set = step.sample ? sampleSet(step.sample) : null;

		function sampleControls() {
			if (!set) return '';

			var undoable = undoSnapshot && undoSnapshot.key === step.sample && undoSnapshot.values.length;
			if (undoable) {
				return '<div class="lpt-sample">' +
					'<span class="lpt-sample-done">サンプル値を入れました</span>' +
					'<button type="button" class="lpt-linkbtn" data-act="sample-undo">元に戻す</button>' +
					'</div>';
			}

			var filled = filledSampleFields(set);

			if (!filled.length) {
				return '<div class="lpt-sample">' +
					'<button type="button" class="lpt-linkbtn" data-act="sample-empty">サンプル値を入れる</button>' +
					'</div>';
			}

			if (filled.length === set.fields.length) {
				return '<div class="lpt-sample">' +
					'<button type="button" class="lpt-linkbtn" data-act="sample-all">すべてサンプルで置き換える</button>' +
					'</div>';
			}

			return '<div class="lpt-sample">' +
				'<button type="button" class="lpt-linkbtn" data-act="sample-empty">空欄だけサンプルで埋める</button>' +
				'<button type="button" class="lpt-linkbtn lpt-linkbtn-sub" data-act="sample-all">すべて置き換える</button>' +
				'</div>';
		}

		function confirmOverwrite() {
			var filled = filledSampleFields(set);
			if (!filled.length) return true;

			var lines = filled.map(function (field) {
				return '・' + field.label + '「' + String(readSampleField(field)).replace(/\n/g, ' / ') + '」';
			});

			return window.confirm(
				'次の項目が入力済みです。サンプル値で上書きしますか？\n\n' +
				lines.join('\n') +
				'\n\n（保存しない限りDBには反映されません。吹き出しの「元に戻す」でも戻せます）'
			);
		}

		function renderTooltipContent() {
			var blocked = isBlocked(step, hit);
			var prevIdx = indexOfId(step.id, list) - 1;
			var canGoPrev = prevIdx >= 0 && list[prevIdx].group === currentScreenId;
			var unmet = unmetRequirement(step);

			tooltipEl.innerHTML =
				'<button type="button" class="lpt-close" aria-label="ガイドを終了">×</button>' +
				'<div class="lpt-tooltip-head">' +
					'<span class="lpt-chapter">章' + step.chapter + '・' + chapterMeta(step.chapter).title + '</span>' +
					'<span class="lpt-progress">' + position + ' / ' + inChapter.length + '</span>' +
				'</div>' +
				'<p class="lpt-title">' + stepNumber(position) + ' ' + step.title + tierBadge(step, hit) + '</p>' +
				'<p class="lpt-body">' + bodyFor(step) + '</p>' +
				(unmet ? '<p class="lpt-note">前の手順がまだ済んでいないため、ここは空のままです。</p>' : '') +
				sampleControls() +
				(step.allowReload
					? '<div class="lpt-sample"><button type="button" class="lpt-linkbtn" data-act="reload">項目が出ていないときは画面を再読み込みする</button></div>'
					: '') +
				(step.navHint ? '<p class="lpt-nav-hint">👉 ハイライトした場所をクリックすると次に進みます</p>' : '') +
				(step.awaitPublish ? '<p class="lpt-nav-hint">👉 確認パネルの「公開」まで押すと、自動で次に進みます</p>' : '') +
				(step.openLp ? '<p class="lpt-nav-hint">👉 下の「LPを開く」から戻れます</p>' : '') +
				'<div class="lpt-footer">' +
					'<div class="lpt-footer-left">' +
						'<button type="button" class="lpt-btn" data-act="prev"' + (canGoPrev ? '' : ' disabled') + '>戻る</button>' +
						(step.openLp
							? '<button type="button" class="lpt-btn lpt-btn-primary" data-act="openlp">LPを開く</button>'
							: '<button type="button" class="lpt-btn lpt-btn-primary" data-act="next"' + (blocked ? ' disabled' : '') + '>' + (step.isFinal ? '完了' : '次へ') + '</button>') +
					'</div>' +
					'<div class="lpt-footer-right">' +
						'<button type="button" class="lpt-skip" data-act="skip-chapter">章をとばす</button>' +
						'<button type="button" class="lpt-skip" data-act="skip">スキップ</button>' +
					'</div>' +
				'</div>' +
				'<div class="lpt-overall">全体 ' + overall + ' / ' + list.length + '</div>';

			bindTooltip(canGoPrev);
		}

		function bindTooltip(canGoPrev) {
			var nextBtn = tooltipEl.querySelector('[data-act="next"]');
			if (nextBtn) {
				nextBtn.addEventListener('click', function () {
					if (!nextBtn.disabled) advance(step.id);
				});
			}

			var openLpBtn = tooltipEl.querySelector('[data-act="openlp"]');
			if (openLpBtn) {
				openLpBtn.addEventListener('click', function () {
					var state = getState() || {};
					var next = nextStepAfter(step.id);
					if (next) setState({ currentId: next.id });
					window.location.href = state.lpId
						? DATA.urls.editPost + state.lpId
						: DATA.urls['edit-service'];
				});
			}

			if (canGoPrev) {
				tooltipEl.querySelector('[data-act="prev"]').addEventListener('click', function () {
					goPrev(step.id);
				});
			}

			tooltipEl.querySelector('[data-act="skip"]').addEventListener('click', function () {
				advance(step.id);
			});

			tooltipEl.querySelector('[data-act="skip-chapter"]').addEventListener('click', function () {
				skipChapter(step);
			});

			tooltipEl.querySelector('.lpt-close').addEventListener('click', endTour);

			['sample-empty', 'sample-all'].forEach(function (act) {
				var btn = tooltipEl.querySelector('[data-act="' + act + '"]');
				if (!btn) return;
				btn.addEventListener('click', function () {
					if (act === 'sample-all' && !confirmOverwrite()) return;
					applySample(step.sample, act === 'sample-all' ? 'all' : 'empty');
					renderTooltipContent();
					positionAround(hit);
				});
			});

			// 読み込み直しても同じステップから再開できるよう、現在位置を書き戻してから遷移する
			var reloadBtn = tooltipEl.querySelector('[data-act="reload"]');
			if (reloadBtn) {
				reloadBtn.addEventListener('click', function () {
					setState({ currentId: step.id });
					window.location.reload();
				});
			}

			var undoBtn = tooltipEl.querySelector('[data-act="sample-undo"]');
			if (undoBtn) {
				undoBtn.addEventListener('click', function () {
					undoSample();
					renderTooltipContent();
					positionAround(hit);
				});
			}
		}

		waitUntilVisible(hit, 15, function () {
			try {
				// smooth だとスクロールの途中で測ってしまうため、即時スクロールにする
				hit.el.scrollIntoView({ block: 'center' });
			} catch (e) { /* noop */ }

			setTimeout(function () {
				if (!tooltipEl) return;
				renderTooltipContent();
				positionAround(hit);
				startTracking(hit);

				// 画像の読み込みやエディタの遅延描画で位置が動くことがあるため、
				// 少し置いてもう一度だけ測り直す。
				setTimeout(function () {
					if (tooltipEl) positionAround(hit);
				}, 500);
			}, 250);
		});

		// 公開ボタンを押すと確認パネルが開くので、ハイライトをそちらへ移す
		if (step.targetPanel) watchPublishPanel(step, hit);

		// 実際に公開が確定したら自動で次へ
		if (step.awaitPublish) watchForPublish(step);

		// 保存が済んだら画面を読み込み直してから次へ
		if (step.awaitSave) watchForSave(step);

		// required ステップは入力されるまで「次へ」を押せない。タイトルは wp.data 側にあり
		// DOMイベントでは拾えないため、表示中だけポーリングして状態を同期する。
		if (step.tier === 'required' && !unmetRequirement(step)) {
			gateTimer = setInterval(function () {
				if (!tooltipEl) return;
				var btn = tooltipEl.querySelector('[data-act="next"]');
				if (!btn) return;
				if (btn.disabled === isBlocked(step, hit)) return;

				renderTooltipContent();

				// 「下書き保存」は保存後に「保存済み」表示へ差し替わり、元の要素が
				// DOMから外れて矩形が0になる。状態が変わったタイミングで測り直す。
				if (step.awaitSave) {
					var refreshed = resolveTarget(step);
					if (refreshed) {
						stopTracking();
						startTracking(refreshed);
					}
				}

				// 確認パネルへハイライトを移している最中は、そちらを維持する
				positionAround(trackedHit || hit);
			}, 400);
		}

		if (step.navHint) {
			hit.el.addEventListener('click', function handoff() {
				if (step.recordLp) recordCurrentPostId('lpId');
				if (step.recordProduct) recordCurrentPostId('productIds');

				var next = nextStepAfter(step.id);
				if (!next) {
					setTimeout(completeTour, 700);
					return;
				}

				setState({ currentId: next.id });

				// 「公開」ボタンはAjax保存で完結しページ遷移しないことがある。
				// 少し待って画面が変わっていなければ、このページの案内を畳んで引き継ぐ。
				setTimeout(function () {
					if (next.chapter !== step.chapter) {
						showChapterOutro(step.chapter, next);
						return;
					}
					if (next.group === currentScreenId) {
						renderStep(next);
						return;
					}
					teardownOverlay();
					showReentryPill(next);
					showToast('保存しました。次は「' + screenLabel(next.group) + '」でご案内します');
				}, 700);
			}, { once: true });
		}
	}

	/**
	 * いま編集中の投稿IDを記録する。章4で「最初に作ったLP」に戻るために使う。
	 */
	function recordCurrentPostId(key) {
		var id = null;
		try {
			id = wp.data.select('core/editor').getCurrentPostId();
		} catch (e) {
			var match = window.location.search.match(/[?&]post=(\d+)/);
			id = match ? parseInt(match[1], 10) : null;
		}
		if (!id) return;

		if (key === 'lpId') {
			setState({ lpId: id });
			return;
		}

		var state = getState() || {};
		var ids = state.productIds || [];
		if (ids.indexOf(id) === -1) ids.push(id);
		setState({ productIds: ids });
	}

	// ---------- 公開の検知 ----------

	/**
	 * 「公開」が確定するのを待つ。確認パネルの2回目のクリックまで済んで初めて
	 * 次へ進むので、「1回押しただけで完了扱い」にならない。
	 */
	function watchForPublish(step) {
		stopPublishWatch();

		// このステップに来た時点ですでに公開済みなら自動では進めず、
		// ユーザーが「次へ」を押すのを待つ（勝手に画面が変わらないように）。
		if (isPostPublished() !== false) return;

		publishWatcher = setInterval(function () {
			if (!tooltipEl) {
				stopPublishWatch();
				return;
			}
			if (isPostPublished() !== true) return;

			stopPublishWatch();
			handlePublished(step);
		}, 400);
	}

	function handlePublished(step) {
		if (step.recordLp) recordCurrentPostId('lpId');
		if (step.recordProduct) recordCurrentPostId('productIds');

		var next = nextStepAfter(step.id);
		if (!next) {
			setTimeout(completeTour, 600);
			return;
		}

		setState({ currentId: next.id });
		showToast('公開しました');

		setTimeout(function () {
			if (next.chapter !== step.chapter) {
				showChapterOutro(step.chapter, next);
				return;
			}
			proceedTo(next);
		}, 900);
	}

	/**
	 * 保存の完了を待つ。クラシックメタボックス（商品の「絞り込み条件」）は
	 * 保存しただけでは中身が作り直されず空のままなので、reloadAfterSave が
	 * 付いているステップでは保存できた時点で画面を読み込み直す。
	 */
	function watchForSave(step) {
		stopSaveWatch();

		if (!step.reloadAfterSave) return;
		if (isPostSaved() !== false) return;

		saveWatcher = setInterval(function () {
			if (!tooltipEl) {
				stopSaveWatch();
				return;
			}
			if (isPostSaved() !== true) return;

			stopSaveWatch();
			handleSaved(step);
		}, 400);
	}

	function handleSaved(step) {
		var next = nextStepAfter(step.id);
		if (next) setState({ currentId: next.id });

		showCard(
			'<p class="lpt-card-eyebrow">保存しました</p>' +
			'<h2 class="lpt-card-title">項目を読み込み直しています…</h2>' +
			'<p class="lpt-card-body">保存した「所属LP」に合わせて「絞り込み条件」の入力欄を用意するため、この画面を読み込み直します。そのままお待ちください。</p>',
			[]
		);

		setTimeout(function () {
			window.location.reload();
		}, 1200);
	}

	/**
	 * 公開確認パネルが開いたら、ハイライトをパネル内の「公開」ボタンへ移す。
	 * ヘッダーのボタンを指したままだと「どこを押せばいいのか」が分からないため。
	 */
	function watchPublishPanel(step, hit) {
		stopPanelWatch();
		var moved = false;

		panelWatcher = setInterval(function () {
			if (!tooltipEl) {
				stopPanelWatch();
				return;
			}

			var el = null;
			for (var i = 0; i < step.targetPanel.length && !el; i++) {
				el = document.querySelector(step.targetPanel[i]);
			}

			var rect = el ? el.getBoundingClientRect() : null;
			var visible = !!rect && (rect.width > 0 || rect.height > 0);

			if (visible && !moved) {
				moved = true;
				trackedHit = { el: el, frame: null };
				positionAround(trackedHit);
			} else if (!visible && moved) {
				moved = false;
				trackedHit = hit;
				positionAround(hit);
			}
		}, 300);
	}

	// ---------- flow control ----------
	function nextStepAfter(fromId) {
		var list = scopedSteps();
		var idx = indexOfId(fromId, list);
		return idx === -1 ? null : (list[idx + 1] || null);
	}

	function proceedTo(step) {
		if (!step) {
			completeTour();
			return;
		}

		setState({ currentId: step.id });

		if (step.group === currentScreenId) {
			renderStep(step);
			return;
		}

		teardownOverlay();
		showReentryPill(step);
	}

	function firstRealStep(chapter) {
		var list = chapterSteps(chapter);
		for (var i = 0; i < list.length; i++) {
			if (!list[i].transition) return list[i];
		}
		return list[0] || null;
	}

	/**
	 * 章の入口URL。章4だけは章1で記録したLPの編集画面へ直接戻る。
	 */
	function chapterEntryUrl(chapter, step) {
		var state = getState() || {};

		if (step && step.group === 'service' && state.lpId) {
			return DATA.urls.editPost + state.lpId;
		}

		var entry = chapterMeta(chapter).entry;
		return (DATA.urls && DATA.urls[entry]) || null;
	}

	/**
	 * 章末カードの「章Nへ進む」。以前は左メニューをハイライトしてユーザーの
	 * クリックを待っていたが、ブロックエディタがフルスクリーンモードだと
	 * 左メニューが非表示になり先へ進めなくなるため、ここで実際に遷移させる。
	 */
	function jumpToChapter(chapter) {
		var step = firstRealStep(chapter);
		if (!step) {
			completeTour();
			return;
		}

		setState({ currentId: step.id });

		if (step.group === currentScreenId) {
			renderStep(step);
			return;
		}

		var url = chapterEntryUrl(chapter, step);
		if (url) {
			window.location.href = url;
			return;
		}

		teardownOverlay();
		showReentryPill(step);
	}

	function advance(fromId) {
		var current = stepById(fromId);
		var next = nextStepAfter(fromId);

		if (!next) {
			completeTour();
			return;
		}

		if (current && next.chapter !== current.chapter) {
			showChapterOutro(current.chapter, next);
			return;
		}

		proceedTo(next);
	}

	function goPrev(fromId) {
		var list = scopedSteps();
		var prev = list[indexOfId(fromId, list) - 1];
		if (!prev || prev.group !== currentScreenId) return;
		setState({ currentId: prev.id });
		renderStep(prev);
	}

	function skipChapter(step) {
		var list = scopedSteps();
		var idx = indexOfId(step.id, list);

		for (var i = idx + 1; i < list.length; i++) {
			if (list[i].chapter !== step.chapter) {
				showChapterOutro(step.chapter, list[i]);
				return;
			}
		}
		completeTour();
	}

	function endTour() {
		clearState();
		teardownOverlay();
		removePill();
	}

	function completeTour() {
		clearState();
		teardownOverlay();
		removePill();
		showToast('🎉 ひと通りご案内しました。お疲れさまでした！');
	}

	function showReentryPill(step) {
		removePill();

		var url = DATA.urls && DATA.urls[step.group];
		var state = getState() || {};

		// 章4のLP編集画面は投稿ごとにURLが変わるため、記録したIDへの直リンクを出す。
		if (!url && step.group === 'service' && state.lpId) {
			url = DATA.urls.editPost + state.lpId;
		}

		pillEl = document.createElement('div');
		pillEl.className = 'lpt-pill';
		pillEl.innerHTML =
			'<strong>ガイドは一時停止中</strong>' +
			'続きは「' + screenLabel(step.group) + '」画面で自動的に再開します。' +
			'<div class="lpt-pill-actions">' +
			(url ? '<a href="' + url + '">画面へ移動</a>' : '') +
			'<button type="button" class="lpt-skip" data-act="end">ガイドを終了</button>' +
			'</div>';

		document.body.appendChild(pillEl);
		pillEl.querySelector('[data-act="end"]').addEventListener('click', endTour);
	}

	// ---------- start / resume ----------
	function startTour(chapterOnly) {
		exitFullscreenMode();

		setState({
			active: true,
			chapterOnly: chapterOnly || null,
			currentId: null,
			introShown: {},
			productIds: [],
			design: PREREQ.designTemplate || '',
			repeating: false
		});

		var first = chapterOnly ? chapterSteps(chapterOnly)[0] : scopedSteps()[0];
		if (!first) return;

		setState({ currentId: first.id });

		if (first.group === currentScreenId) {
			renderStep(first);
			return;
		}

		var entry = chapterOnly ? chapterMeta(chapterOnly).entry : first.group;
		var url = DATA.urls && DATA.urls[entry];

		if (url) {
			window.location.href = url;
		} else {
			showReentryPill(first);
		}
	}

	function resume() {
		var state = getState();
		if (!state || !state.active || !state.currentId) return false;

		var step = stepById(state.currentId);
		if (!step) {
			clearState();
			return false;
		}

		if (step.group === currentScreenId) {
			renderStep(step);
		} else {
			showReentryPill(step);
		}
		return true;
	}

	// ---------- boot ----------
	document.addEventListener('DOMContentLoaded', function () {
		var triggers = document.querySelectorAll('#wp-admin-bar-lp-service-tour a');

		Array.prototype.forEach.call(triggers, function (link) {
			link.addEventListener('click', function (e) {
				var href = link.getAttribute('href') || '';
				var chapterMatch = href.match(/#lpt-chapter-(\d+)/);

				if (!chapterMatch && href.indexOf('#lpt-start') === -1) return;
				e.preventDefault();

				if (chapterMatch) {
					startTour(parseInt(chapterMatch[1], 10));
					return;
				}

				// 中断していれば続きから、そうでなければ最初から
				if (!resume()) startTour(null);
			});
		});

		// デザインテンプレートを切り替えたら、以降のステップ出し分けに反映する
		var designSelect = document.getElementById('lp_design_template');
		if (designSelect) {
			designSelect.addEventListener('change', function () {
				var state = getState();
				if (state && state.active) setState({ design: designSelect.value });
			});
		}

		var resumed = resume();

		// ブロックエディタは body のクラスやストアの初期化が DOMContentLoaded より
		// 後になることがあるため、少し遅れてもう一度だけ解除を試みる。
		if (resumed) {
			setTimeout(exitFullscreenMode, 800);
			setTimeout(exitFullscreenMode, 2000);
		}
	});
})();
