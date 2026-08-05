<style>
/* 예약 운영 화면 — Pretendard / #2677e3, 관리자 리디자인 톤 */
.rsva { font-family: 'Pretendard Variable', Pretendard, -apple-system, BlinkMacSystemFont, system-ui, sans-serif; word-break: keep-all; color: #1c2330; }
.rsva-table td { color: #1c2330; }
.rsva-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-bottom: 20px; }
.rsva-card { padding: 18px 20px; border: 1px solid #e5e8ee; border-radius: 14px; background: #fff; }
.rsva-card b { display: block; font-size: 26px; font-weight: 800; color: #2677e3; }
.rsva-card span { font-size: 13px; color: #6b7684; }
.rsva-table { width: 100%; border-collapse: collapse; background: #fff; border: 1px solid #e5e8ee; border-radius: 12px; overflow: hidden; }
.rsva-table th { padding: 10px 12px; background: #f7f8fa; font-size: 13px; font-weight: 600; color: #6b7684; text-align: left; border-bottom: 1px solid #e5e8ee; }
.rsva-table td { padding: 11px 12px; font-size: 13px; border-bottom: 1px solid #f0f2f5; vertical-align: middle; }
.rsva-table tr:last-child td { border-bottom: 0; }
.rsva-st { display: inline-block; padding: 2px 9px; border-radius: 999px; font-size: 12px; font-weight: 600; background: #f2f3f5; color: #6b7684; }
.rsva-st-confirmed { background: rgba(38,119,227,.1); color: #2677e3; }
.rsva-st-hold, .rsva-st-pending { background: #fdf3e2; color: #b97a17; }
.rsva-st-cancelled, .rsva-st-expired { background: #fdeaea; color: #c0392b; }
.rsva-filter { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 14px; }
.rsva-filter select, .rsva-filter input { padding: 7px 10px; border: 1px solid #e5e8ee; border-radius: 8px; font-size: 13px; font-family: inherit; }
/* 관리자 전역 a/버튼 색 규칙이 특이도로 덮으므로 색은 !important 로 고정한다 */
.rsva-btn { display: inline-flex; align-items: center; gap: 5px; padding: 7px 13px; border: 1px solid #e5e8ee; border-radius: 9px; background: #fff !important; font-size: 13px; font-weight: 600; font-family: inherit; cursor: pointer; color: #1c2330 !important; text-decoration: none !important; }
.rsva-btn:hover { border-color: #2677e3; color: #2677e3 !important; }
.rsva-btn-primary { background: #2677e3 !important; border-color: #2677e3; color: #fff !important; }
.rsva-btn-primary:hover { filter: brightness(1.06); color: #fff !important; }
.rsva-btn-sm { padding: 4px 9px; font-size: 12px; border-radius: 7px; }
.rsva-btn-danger:hover { border-color: #e5484d; color: #e5484d !important; }
.rsva-panel { padding: 18px 20px; border: 1px solid #e5e8ee; border-radius: 14px; background: #fff; margin-bottom: 16px; }
.rsva-panel h3 { margin: 0 0 12px; font-size: 15px; font-weight: 700; }
.rsva-form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; }
.rsva-form-grid label, .rsva-field label { display: block; margin-bottom: 5px; font-size: 13px; font-weight: 600; }
.rsva-form-grid input, .rsva-form-grid select, .rsva-form-grid textarea,
.rsva-field input, .rsva-field select, .rsva-field textarea { width: 100%; box-sizing: border-box; padding: 8px 10px; border: 1px solid #e5e8ee; border-radius: 8px; font-size: 13px; font-family: inherit; }
.rsva-field { margin-bottom: 12px; }
.rsva-inline { display: flex; flex-wrap: wrap; gap: 8px; align-items: flex-end; }
.rsva-inline > div { min-width: 90px; }
.rsva-empty { padding: 32px 0; text-align: center; color: #6b7684; font-size: 13px; }
.rsva-weekdays { display: flex; gap: 6px; flex-wrap: wrap; }
.rsva-weekdays label { display: inline-flex; align-items: center; gap: 4px; padding: 5px 9px; border: 1px solid #e5e8ee; border-radius: 8px; font-size: 12px; cursor: pointer; margin: 0; font-weight: 500; }
@media (max-width: 768px) { .rsva-table { display: block; overflow-x: auto; } }
</style>

@if (!empty($zmc_console))
<style>
@font-face { font-family: 'Pretendard'; src: url('{{ \RX_BASEURL }}common/fonts/PretendardVariable.woff2') format('woff2-variations'); font-weight: 45 920; font-display: swap; }
:root { --zmc-brand: #2677e3; --zmc-brand-soft: #eef4fd; --zmc-ink: #191f28; --zmc-sub: #6b7684; --zmc-line: #e5e8eb; --zmc-bg: #f5f7fa; --zmc-font: 'Pretendard', -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Malgun Gothic', sans-serif; }
html, body { margin: 0; padding: 0; background: var(--zmc-bg); }
body, body * { font-family: var(--zmc-font); font-style: normal; }
body { -webkit-font-smoothing: antialiased; color: var(--zmc-ink); }
/* ── 사이드바 ── */
.zmc-side { position: fixed; top: 0; left: 0; bottom: 0; width: 232px; box-sizing: border-box; padding: 24px 16px 16px; background: #fff; border-right: 1px solid var(--zmc-line); z-index: 100; overflow-y: auto; display: flex; flex-direction: column; }
.zmc-logo { display: flex; align-items: baseline; gap: 7px; padding: 0 10px 22px; font-size: 18px; font-weight: 800; color: var(--zmc-ink); letter-spacing: -0.02em; }
.zmc-logo b { color: var(--zmc-brand); font-weight: 800; }
.zmc-logo span { font-size: 14px; font-weight: 700; color: var(--zmc-sub); }
.zmc-nav { flex: 1; }
.zmc-nav a { position: relative; display: flex; align-items: center; gap: 10px; padding: 10px 14px; margin-bottom: 3px; border-radius: 10px; font-size: 14.5px; font-weight: 600; font-style: normal; color: #4e5968 !important; text-decoration: none !important; transition: background .12s, color .12s; }
.zmc-nav a span { font-style: normal; }
.zmc-nav a:hover { background: #f4f6f9; color: var(--zmc-ink) !important; }
.zmc-nav a.is-active { background: var(--zmc-brand-soft); color: var(--zmc-brand) !important; font-weight: 700; }
.zmc-nav a.is-active::before { content: ''; position: absolute; left: 0; top: 9px; bottom: 9px; width: 3px; border-radius: 3px; background: var(--zmc-brand); }
.zmc-side-foot { padding-top: 14px; border-top: 1px solid var(--zmc-line); }
.zmc-side-foot a { display: block; padding: 7px 14px; border-radius: 8px; font-size: 12.5px; font-weight: 500; color: #8b95a1 !important; text-decoration: none !important; }
.zmc-side-foot a:hover { color: var(--zmc-brand) !important; background: #f4f6f9; }
/* ── 상단 바 + 콘텐츠 ── */
.zmc-top { position: sticky; top: 0; z-index: 90; margin-left: 232px; padding: 16px 36px; background: rgba(255,255,255,.92); backdrop-filter: blur(6px); border-bottom: 1px solid var(--zmc-line); display: flex; align-items: center; justify-content: space-between; }
.zmc-top h2 { margin: 0; font-size: 19px; font-weight: 800; letter-spacing: -0.02em; }
.rsva { margin-left: 232px; padding: 28px 36px 100px; box-sizing: border-box; min-height: calc(100vh - 60px); }
/* ── 콘솔 전용 컴포넌트 재정의 (카드 섹션 + 넉넉한 입력) ── */
.rsva { font-size: 14px; color: var(--zmc-ink); }
.rsva .rsva-panel { padding: 26px 28px; border: 1px solid var(--zmc-line); border-radius: 16px; background: #fff; margin-bottom: 18px; box-shadow: 0 1px 2px rgba(25,31,40,.03); }
.rsva .rsva-panel > h3 { margin: 0 0 18px; padding-bottom: 14px; border-bottom: 1px solid #f0f2f5; font-size: 16px; font-weight: 800; letter-spacing: -0.01em; }
.rsva .rsva-form-grid { gap: 16px 18px; }
.rsva label { display: block; font-size: 13px; font-weight: 700; color: #333d4b; margin-bottom: 7px; }
.rsva .rsva-inline { gap: 14px 18px; }
.rsva input[type="text"], .rsva input[type="number"], .rsva input[type="date"], .rsva input[type="datetime-local"], .rsva input[type="time"], .rsva input[type="email"], .rsva input[type="tel"], .rsva input[type="password"], .rsva select, .rsva textarea { box-sizing: border-box; padding: 10px 12px; border: 1px solid #dde3ec; border-radius: 10px; font-size: 14px; background: #fff; color: var(--zmc-ink); transition: border-color .12s, box-shadow .12s; }
.rsva input:focus, .rsva select:focus, .rsva textarea:focus { outline: none; border-color: var(--zmc-brand); box-shadow: 0 0 0 3px rgba(38,119,227,.12); }
.rsva .rsva-btn { padding: 9px 16px; border-radius: 10px; font-size: 14px; border-color: #dde3ec; }
.rsva .rsva-btn:hover { border-color: var(--zmc-brand); }
.rsva .rsva-btn-primary { box-shadow: 0 1px 3px rgba(38,119,227,.3); }
.rsva .rsva-btn-sm { padding: 6px 11px; font-size: 12.5px; border-radius: 8px; }
.rsva .rsva-table { border-radius: 14px; border-color: var(--zmc-line); }
.rsva .rsva-table th { padding: 12px 14px; font-size: 12.5px; letter-spacing: .01em; }
.rsva .rsva-table td { padding: 13px 14px; font-size: 13.5px; }
.rsva .rsva-table tbody tr:hover td { background: #fafbfc; }
.rsva .rsva-filter { padding: 14px 16px; background: #fff; border: 1px solid var(--zmc-line); border-radius: 14px; margin-bottom: 16px; }
.rsva .rsva-card { border-radius: 16px; box-shadow: 0 1px 2px rgba(25,31,40,.03); }
.rsva small { color: var(--zmc-sub); }
@media (max-width: 900px) { .zmc-side { width: 62px; padding: 18px 9px; } .zmc-logo { padding: 0 6px 16px; } .zmc-logo span, .zmc-nav a span, .zmc-side-foot { display: none; } .zmc-top { margin-left: 62px; padding: 14px 16px; } .rsva { margin-left: 62px; padding: 18px 16px 70px; } }
</style>
@php
$zmc_menu = [
	'dashboard' => $lang->rsv_tab_dashboard, 'bookings' => $lang->rsv_tab_bookings, 'resources' => $lang->rsv_tab_resources,
	'schedule' => $lang->rsv_tab_schedule, 'forms' => $lang->rsv_tab_forms, 'stats' => $lang->rsv_tab_stats, 'config' => $lang->rsv_tab_config,
];
$zmc_active_alias = ['resource_edit' => 'resources'];
$zmc_current = $zmc_active_alias[$zmc_page] ?? $zmc_page;
@endphp
<aside class="zmc-side">
	<div class="zmc-logo"><b>zittme</b> <span>예약 콘솔</span></div>
	<nav class="zmc-nav">
		@foreach ($zmc_menu as $key => $label)
		<a href="{{ getUrl('', 'module', '', 'mid', '', 'act', 'dispReservationConsole', 'p', $key) }}" class="{{ $zmc_current === $key ? 'is-active' : '' }}"><span>{{ $label }}</span></a>
		@endforeach
	</nav>
	<div class="zmc-side-foot">
		<a href="{{ getUrl('', 'module', '', 'mid', '', 'act', '') }}" target="_blank">사이트 보기</a>
		<a href="{{ getUrl('', 'mid', '', 'module', 'admin', 'act', '') }}" target="_blank">zittme 관리자</a>
	</div>
</aside>
<div class="zmc-top"><h2>{{ $zmc_menu[$zmc_current] ?? '' }}</h2></div>
<script>
(function () {
	function toP(n) { return n.replace(/([a-z0-9])([A-Z])/g, '$1_$2').toLowerCase(); }
	function rewrite() {
		document.querySelectorAll('a[href*="dispReservationAdmin"]').forEach(function (a) {
			var m = a.getAttribute('href').match(/dispReservationAdmin([A-Za-z]+)/);
			if (!m) return;
			var u = new URL(a.href, location.href);
			u.searchParams.delete('module');
			u.searchParams.set('act', 'dispReservationConsole');
			u.searchParams.set('p', toP(m[1]));
			a.href = u.toString();
		});
		document.querySelectorAll('form').forEach(function (f) {
			var act = f.querySelector('input[name="act"]');
			if (!act) return;
			var m = act.value.match(/^dispReservationAdmin([A-Za-z]+)$/);
			if (m) {
				act.value = 'dispReservationConsole';
				var mod = f.querySelector('input[name="module"]');
				if (mod) mod.remove();
				var p = f.querySelector('input[name="p"]');
				if (!p) { p = document.createElement('input'); p.type = 'hidden'; p.name = 'p'; f.appendChild(p); }
				p.value = toP(m[1]);
			} else if (/^proc/.test(act.value)) {
				var s = f.querySelector('input[name="success_return_url"]');
				if (!s) { s = document.createElement('input'); s.type = 'hidden'; s.name = 'success_return_url'; f.appendChild(s); }
				s.value = location.href;
			}
		});
	}
	document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', rewrite) : rewrite();
})();
</script>
@else
<div class="x_page-header rsva">
	<h1>{{ $lang->reservation }}</h1>
</div>

<div class="rsva" style="margin:0 0 14px;padding:16px 20px;border:1px solid rgba(38,119,227,.35);border-radius:12px;background:#f2f6fd;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
	<div style="font-size:13.5px;color:#1c2330"><b style="color:#2677e3">예약 전용 콘솔</b>에서 관리하세요. 예약·상품·일정 운영은 별도 패널로 제공됩니다.</div>
	<a href="{{ getUrl('', 'module', '', 'act', 'dispReservationConsole') }}" target="_blank" class="rsva-btn rsva-btn-primary" id="zmcOpenConsole">콘솔 새탭으로 열기</a>
</div>
@if ($rsv_tab === 'dashboard')
<script>
(function () {
	try {
		if (!sessionStorage.getItem('zmcRsvConsoleOpened')) {
			sessionStorage.setItem('zmcRsvConsoleOpened', '1');
			var btn = document.getElementById('zmcOpenConsole');
			if (btn) window.open(btn.href, '_blank');
		}
	} catch (e) {}
})();
</script>
@endif

<ul class="x_nav x_nav-tabs rsva">
	<li @if($rsv_tab === 'dashboard') class="x_active" @endif><a href="{{ getUrl('', 'module', 'admin', 'act', 'dispReservationAdminDashboard') }}">{{ $lang->rsv_tab_dashboard }}</a></li>
	<li @if($rsv_tab === 'bookings') class="x_active" @endif><a href="{{ getUrl('', 'module', 'admin', 'act', 'dispReservationAdminBookings') }}">{{ $lang->rsv_tab_bookings }}</a></li>
	<li @if($rsv_tab === 'resources') class="x_active" @endif><a href="{{ getUrl('', 'module', 'admin', 'act', 'dispReservationAdminResources') }}">{{ $lang->rsv_tab_resources }}</a></li>
	<li @if($rsv_tab === 'schedule') class="x_active" @endif><a href="{{ getUrl('', 'module', 'admin', 'act', 'dispReservationAdminSchedule') }}">{{ $lang->rsv_tab_schedule }}</a></li>
	<li @if($rsv_tab === 'forms') class="x_active" @endif><a href="{{ getUrl('', 'module', 'admin', 'act', 'dispReservationAdminForms') }}">{{ $lang->rsv_tab_forms }}</a></li>
	<li @if($rsv_tab === 'stats') class="x_active" @endif><a href="{{ getUrl('', 'module', 'admin', 'act', 'dispReservationAdminStats') }}">{{ $lang->rsv_tab_stats }}</a></li>
	<li @if($rsv_tab === 'config') class="x_active" @endif><a href="{{ getUrl('', 'module', 'admin', 'act', 'dispReservationAdminConfig') }}">{{ $lang->rsv_tab_config }}</a></li>
</ul>
@endif
