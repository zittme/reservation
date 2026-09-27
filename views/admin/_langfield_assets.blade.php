{{-- 다국어 입력 도우미 공통 자산 — 화면당 한 번만 넣는다 --}}
{{-- 패널은 페이지에 하나만 두고 어떤 입력이든 옆의 버튼으로 불러 쓴다 (JS 로 만든 행에도 붙는다) --}}
<style>
/* 관리자 전역 button 규칙이 특이도로 덮으므로 크기·모양을 고정한다 */
.zlf-btn { flex: 0 0 auto !important; display: inline-flex !important; align-items: center; justify-content: center; width: 36px !important; height: 36px !important; min-width: 0 !important; padding: 0 !important; margin: 0 !important; border: 1px solid #e5e8ee !important; border-radius: 8px !important; background: #fff !important; color: #8b95a1 !important; cursor: pointer; line-height: 1 !important; vertical-align: middle; box-shadow: none !important; align-self: stretch; }
.zlf-btn svg { width: 16px !important; height: 16px !important; display: block; flex: none; }
.zlf-btn:hover { border-color: #2677e3 !important; color: #2677e3 !important; }
.zlf-btn.is-on { border-color: #2677e3 !important; background: rgba(38,119,227,.08) !important; color: #2677e3 !important; }
.zlf-row-wrap { display: flex; gap: 6px; align-items: stretch; }
.zlf-row-wrap > input, .zlf-row-wrap > textarea { flex: 1 1 auto; min-width: 0; }
/* 표·패널에 overflow:hidden 이 걸린 화면이 있어 절대배치는 잘린다 — 화면 기준으로 띄운다 */
#zlfPanel { display: none; position: fixed; z-index: 2000; width: 320px; padding: 12px; border: 1px solid #e5e8ee; border-radius: 12px; background: #fff; box-shadow: 0 16px 40px -18px rgba(16,24,40,.45); font-size: 13px; color: #1c2330; text-align: left; }
#zlfPanel.is-open { display: block; }
.zlf-tabs { display: flex; gap: 4px; margin-bottom: 10px; }
.zlf-tabs button { flex: 1; padding: 6px 0; border: 1px solid #e5e8ee; border-radius: 8px; background: #fff; font-family: inherit; font-size: 12.5px; color: #6b7684; cursor: pointer; }
.zlf-tabs button.is-on { border-color: #1f6ad0; background: #1f6ad0; color: #fff; font-weight: 700; }
.zlf-search { width: 100%; box-sizing: border-box; padding: 7px 9px; margin-bottom: 8px; border: 1px solid #e5e8ee; border-radius: 8px; font-size: 12.5px; font-family: inherit; }
.zlf-list { max-height: 210px; overflow-y: auto; margin: 0; padding: 0; list-style: none; }
.zlf-list li { padding: 7px 9px; border-radius: 7px; font-size: 12.5px; cursor: pointer; }
.zlf-list li:hover { background: #f2f6fd; }
.zlf-list li small { display: block; color: #8b95a1; font-size: 11px; }
.zlf-list li.zlf-empty { color: #8b95a1; cursor: default; }
.zlf-list li.zlf-empty:hover { background: none; }
.zlf-row { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; }
.zlf-row span { flex: 0 0 76px; color: #6b7684; font-size: 12.5px; }
.zlf-row input { flex: 1; min-width: 0; box-sizing: border-box; padding: 6px 8px; border: 1px solid #e5e8ee; border-radius: 7px; font-size: 12.5px; font-family: inherit; }
.zlf-hint { margin: 6px 0 0; font-size: 11.5px; color: #8b95a1; line-height: 1.6; }
.zlf-foot { margin-top: 10px; text-align: right; }
.zlf-save { padding: 7px 14px; border: 0; border-radius: 8px; background: #1f6ad0; color: #fff; font-family: inherit; font-size: 12.5px; font-weight: 700; cursor: pointer; }
.zlf-current { display: flex; align-items: center; gap: 6px; margin-top: 10px; padding-top: 10px; border-top: 1px solid #f0f2f5; font-size: 12px; color: #6b7684; }
.zlf-current b { color: #2677e3; font-weight: 700; }
.zlf-current button { margin-left: auto; padding: 4px 9px; border: 1px solid #e5e8ee; border-radius: 7px; background: #fff; font-family: inherit; font-size: 11.5px; color: #6b7684; cursor: pointer; }
.zlf-current button + button { margin-left: 4px; }
.zlf-current button:hover { border-color: #2677e3; color: #2677e3; }
</style>

<div id="zlfPanel" aria-hidden="true">
	<div class="zlf-tabs">
		<button type="button" class="is-on" data-lf-tab="pick">{{ $lang->rsv_adm_lang_tab_pick }}</button>
		<button type="button" data-lf-tab="new">{{ $lang->rsv_adm_lang_tab_new }}</button>
	</div>
	<div data-lf-body="pick">
		<input type="search" class="zlf-search" placeholder="{{ $lang->rsv_adm_lang_search }}" id="zlfSearch" />
		<ul class="zlf-list" id="zlfList"><li class="zlf-empty">{{ $lang->rsv_adm_lang_loading }}</li></ul>
	</div>
	<div data-lf-body="new" hidden>
		@foreach (Zittme\Modules\Reservation\Models\Lang::languages() as $zlf_code => $zlf_label)
		<label class="zlf-row"><span>{{ $zlf_label }}</span><input type="text" data-lf-input="{{ $zlf_code }}" /></label>
		@endforeach
		@if (count(Zittme\Modules\Reservation\Models\Lang::languages()) < 2)
		<p class="zlf-hint">{{ $lang->rsv_adm_lang_one_only }}</p>
		@endif
		<div class="zlf-foot"><button type="button" class="zlf-save" id="zlfSave">{{ $lang->rsv_adm_lang_save_use }}</button></div>
	</div>
	<div class="zlf-current" id="zlfCurrent" hidden>
		<span>{{ $lang->rsv_adm_lang_linked }} <b id="zlfCodeName"></b></span>
		<button type="button" id="zlfEdit">{{ $lang->rsv_adm_lang_edit }}</button>
		<button type="button" id="zlfClear">{{ $lang->rsv_adm_lang_unlink }}</button>
	</div>
</div>

<script>
jQuery(function ($) {
	// 값 자체는 코어 규약대로 저장한다. 화면에는 코드 이름만 오간다 —
	// 접두어를 HTML 에 내보내면 코어의 출력 치환에 걸려 값이 깨지기 때문이다.
	var panel = document.getElementById('zlfPanel');
	if (!panel) return;

	var listEl = document.getElementById('zlfList');
	var searchEl = document.getElementById('zlfSearch');
	var currentEl = document.getElementById('zlfCurrent');
	var codeNameEl = document.getElementById('zlfCodeName');
	var target = null;   // {input, hidden, button}

	function close() {
		panel.classList.remove('is-open');
		panel.setAttribute('aria-hidden', 'true');
		target = null;
	}

	function place(button) {
		var rect = button.getBoundingClientRect();
		var width = panel.offsetWidth || 320;
		var height = panel.offsetHeight || 300;
		var left = Math.min(Math.max(8, rect.left), window.innerWidth - width - 8);
		var top = rect.bottom + 6;
		if (top + height > window.innerHeight - 8) top = Math.max(8, rect.top - height - 6);
		panel.style.left = left + 'px';
		panel.style.top = top + 'px';
	}

	function showTab(tab) {
		panel.querySelectorAll('[data-lf-tab]').forEach(function (btn) {
			btn.classList.toggle('is-on', btn.getAttribute('data-lf-tab') === tab);
		});
		panel.querySelectorAll('[data-lf-body]').forEach(function (body) {
			body.hidden = body.getAttribute('data-lf-body') !== tab;
		});
		if (tab === 'pick') loadList();
		if (target) place(target.button);
	}

	function loadList() {
		listEl.innerHTML = '<li class="zlf-empty">' + {!! json_encode($lang->rsv_adm_lang_loading) !!} + '</li>';
		exec_json('reservation.procReservationAdminGetLangCodes', { keyword: searchEl.value }, function (ret) {
			var codes = ret.codes || [];
			if (!codes.length) {
				listEl.innerHTML = '<li class="zlf-empty">' + {!! json_encode($lang->rsv_adm_lang_empty) !!} + '</li>';
				return;
			}
			listEl.innerHTML = '';
			codes.forEach(function (row) {
				var li = document.createElement('li');
				li.innerHTML = '<b></b><small></small>';
				li.querySelector('b').textContent = row.value || row.code;
				li.querySelector('small').textContent = row.code;
				li.addEventListener('click', function () { apply(row.code, row.value); });
				listEl.appendChild(li);
			});
		}, function () {
			listEl.innerHTML = '<li class="zlf-empty">' + {!! json_encode($lang->rsv_adm_lang_list_failed) !!} + '</li>';
		});
	}

	function apply(code, value) {
		if (!target) return;
		target.hidden.value = code || '';
		target.button.classList.toggle('is-on', !!code);
		if (code) {
			target.input.value = value || code;
			target.input.readOnly = true;
		} else {
			target.input.value = '';
			target.input.readOnly = false;
			target.input.focus();
		}
		target.input.dispatchEvent(new Event('change', { bubbles: true }));
		close();
	}

	panel.querySelectorAll('[data-lf-tab]').forEach(function (btn) {
		btn.addEventListener('click', function () { showTab(btn.getAttribute('data-lf-tab')); });
	});

	var timer = null;
	searchEl.addEventListener('input', function () {
		clearTimeout(timer);
		timer = setTimeout(loadList, 250);
	});

	document.getElementById('zlfSave').addEventListener('click', function () {
		var values = {};
		panel.querySelectorAll('[data-lf-input]').forEach(function (el) {
			values[el.getAttribute('data-lf-input')] = el.value;
		});
		exec_json('reservation.procReservationAdminSaveLangCode', {
			code: target ? target.hidden.value : '',
			values: values
		}, function (ret) {
			apply(ret.code, ret.value);
		}, function (ret) {
			alert((ret && ret.message) || {!! json_encode($lang->rsv_adm_lang_save_failed) !!});
		});
	});

	document.getElementById('zlfEdit').addEventListener('click', function () {
		if (!target || !target.hidden.value) return;
		exec_json('reservation.procReservationAdminGetLangCode', { code: target.hidden.value }, function (ret) {
			var values = ret.values || {};
			panel.querySelectorAll('[data-lf-input]').forEach(function (el) {
				el.value = values[el.getAttribute('data-lf-input')] || '';
			});
			showTab('new');
		});
	});

	document.getElementById('zlfClear').addEventListener('click', function () { apply('', ''); });

	function open(button, input, hidden) {
		target = { button: button, input: input, hidden: hidden };
		panel.classList.add('is-open');
		panel.setAttribute('aria-hidden', 'false');
		codeNameEl.textContent = hidden.value || '';
		currentEl.hidden = !hidden.value;
		panel.querySelectorAll('[data-lf-input]').forEach(function (el) { el.value = ''; });
		showTab('pick');
		place(button);
	}

	// 버튼 하나를 입력칸에 잇는다. 동적으로 만든 행에서도 이걸 부르면 된다.
	window.zlfBind = function (button) {
		if (!button || button.zlfBound) return;
		button.zlfBound = true;
		var wrap = button.parentNode;
		var input = wrap.querySelector('input[type="text"], input[type="search"], textarea');
		var hidden = wrap.querySelector('[data-lf-code]');
		if (!input || !hidden) return;
		if (hidden.value) {
			button.classList.add('is-on');
			var display = button.getAttribute('data-lf-display');
			if (display) input.value = display;
			input.readOnly = true;
		}
		button.addEventListener('click', function () {
			if (panel.classList.contains('is-open') && target && target.button === button) { close(); return; }
			open(button, input, hidden);
		});
	};

	// 화면에 이미 있는 버튼들을 잇는다
	document.querySelectorAll('[data-lf-open]').forEach(window.zlfBind);

	document.addEventListener('click', function (e) {
		if (e.target.closest('#zlfPanel') || e.target.closest('[data-lf-open]')) return;
		close();
	});
	window.addEventListener('resize', function () { if (target) place(target.button); });
	window.addEventListener('scroll', function () { if (target) place(target.button); }, true);
});
</script>
