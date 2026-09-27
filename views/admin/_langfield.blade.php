{{-- 다국어 입력 도우미 버튼 — 패널은 _langfield_assets 의 것을 함께 쓴다 --}}
{{-- 쓰는 쪽에서 lf_name(폼 필드 이름), lf_value(현재 저장값), lf_form(선택) 을 넘긴다 --}}
{{-- 폼에는 <필드>_langcode 가 함께 실려 가고, 서버가 이 값이 있으면 코어 규약의 접두어를 붙여 저장한다 --}}
@php
	$lf_code = Zittme\Modules\Reservation\Models\Lang::codeOf($lf_value ?? '');
	$lf_display = $lf_code !== '' ? Zittme\Modules\Reservation\Models\Lang::display($lf_code) : '';
@endphp
<input type="hidden" name="{{ $lf_name }}_langcode" value="{{ $lf_code }}" data-lf-code @if(!empty($lf_form)) form="{{ $lf_form }}" @endif />
<button type="button" class="zlf-btn" data-lf-open data-lf-display="{{ $lf_display }}" title="{{ $lang->rsv_adm_lang_link }}">
	<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="8.5"/><path d="M3.5 12h17"/><path d="M12 3.5c2.3 2.6 2.3 14.4 0 17"/><path d="M12 3.5c-2.3 2.6-2.3 14.4 0 17"/></svg>
</button>
