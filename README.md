# Zittme Reservation

[Zittme](https://github.com/zittme/zittme) 엔진용 예약 모듈입니다. 시설·서비스 예약 페이지를 만들고, 일정 규칙과 예약을 전용 운영 콘솔에서 관리합니다.

## 요구 사항

- Zittme 0.0.01 이상
- 예약금·결제 기능을 쓰려면 [zittme-pay](https://github.com/zittme/zittme_pay) 모듈이 필요합니다. 없어도 예약 자체는 동작하며, 결제 기능만 비활성화됩니다.

## 설치

Zittme 설치 경로의 `modules/reservation` 에 이 저장소의 내용을 놓습니다.

```bash
cd 설치경로/modules
git clone https://github.com/zittme/reservation.git reservation
```

압축 파일로 받았다면 `modules/reservation/` 에 풀면 됩니다. 이후 관리자 화면에 접속하면 테이블 생성과 기본 설정이 자동으로 진행됩니다.

## 주요 기능

- 리소스(시설·서비스) 관리와 일정 규칙(요일·시간대·정원)
- 휴무일, 슬롯 단위 예약, 정원·중복 방지
- 예약 폼 필드 구성 (예약자에게 받을 항목 정의)
- 예약 대기(홀드)와 만료 처리
- 회원·비회원 예약, 내 예약 조회
- 관리자 대시보드·통계와 별도 운영 콘솔
- 스킨 방식의 프론트 화면 (기본 스킨 포함)

## 라이선스

[GPL v2](LICENSE)

## 문의

- 홈페이지: https://zitt.me
- 매뉴얼: https://zitt.me/manual
