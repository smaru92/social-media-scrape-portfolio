# Social Media Platform - TikTok 관리 시스템 포트폴리오

TikTok 인플루언서 관리 및 자동화를 위한 풀스택 플랫폼

## 프로젝트 개요

이 프로젝트는 TikTok 인플루언서와의 협업을 관리하고, 영상 데이터를 수집하며, DM 캠페인을 자동화하는 종합 관리 시스템입니다.
관리자 웹 패널과 데이터 수집 API 서버로 구성된 마이크로서비스 아키텍처를 채택하고 있습니다.

## 시스템 아키텍처

```
Admin Panel (Laravel + Filament)
    ↓ API Call
Backend API (FastAPI + Playwright)
    ↓
  MySQL
```

## 프로젝트 구성

### 1. Admin Panel (Laravel + Filament)
**위치**: `/admin-panel`

관리자를 위한 웹 기반 관리 패널로, 사용자 친화적인 인터페이스를 제공합니다.

**주요 기능**:
- 대시보드: 실시간 통계 및 현황 모니터링
- 사용자 관리: TikTok 인플루언서 정보 관리 및 상태 추적
- 메시지 캠페인: 템플릿 기반 DM 발송 및 결과 추적
- 영상 관리: 업로드 요청 생성 및 진행 상황 관리
- Excel 연동: 대량 데이터 업로드/다운로드
- 고급 필터링: 상태, 국가, 분류별 필터링

**기술 스택**:
- Backend: Laravel 11
- Admin Framework: Filament v3

### 2. Backend API (FastAPI + Playwright)
**위치**: `/backend-api`

TikTok 데이터 수집 및 자동화를 담당하는 고성능 비동기 API 서버입니다.

**주요 기능**:
- 사용자 스크랩: 키워드 기반 TikTok 사용자 검색 및 정보 수집
- 영상 스크랩: 사용자별 영상 데이터 자동 수집
- 리포스트 추적: 브랜드 계정의 리포스트 영상 및 원본 사용자 정보 수집
- DM 자동화: 템플릿 기반 대량 메시지 발송
- 세션 관리: TikTok 로그인 세션 저장 및 재사용
- 이미지 처리: 프로필 및 썸네일 이미지 자동 다운로드

**기술 스택**:
- Framework: FastAPI (비동기 처리)
- Automation: Playwright (브라우저 자동화)
- Database: MySQL + SQLAlchemy ORM

## 데이터베이스 설계

### 핵심 테이블

#### tiktok_users
TikTok 사용자 프로필 및 협업 관리
- username (계정명)
- nickname (표시 이름)
- followers (팔로워 수)
- bio (자기소개)
- country (국가 코드)
- status (진행 상태)
- is_collaborator (협업 동의 여부)

#### tiktok_videos
사용자별 영상 메타데이터

#### tiktok_messages
메시지 캠페인 관리

#### tiktok_brand_accounts
브랜드 계정 정보

#### tiktok_repost_videos
리포스트 영상 추적

## 주요 워크플로우

### 1. 인플루언서 발굴
```
키워드 입력 → TikTok 스크랩 → 자동 저장 → 필터링 → 검토 및 승인
```

### 2. DM 캠페인
```
템플릿 작성 → 대상 선택 → 자동 발송 → 실시간 로깅 → 통계 업데이트
```

### 3. 영상 관리
```
업로드 요청 생성 → 마감일 설정 → 영상 업로드 → 자동 스크랩 → 확인 처리
```

### 4. 리포스트 분석
```
브랜드 계정 등록 → 리포스트 수집 → 원본 사용자 추출 → 자동 저장
```

## 설치 및 실행

### 사전 요구사항
- MySQL 8.0+, Git
- Admin Panel: PHP 8.2+
- Backend API: Python 3.11+, Chrome/Chromium

### Admin Panel 설치
```bash
cd admin-panel

# 의존성 설치
composer install
npm install

# 환경 설정
cp .env.example .env
php artisan key:generate

# 데이터베이스 마이그레이션
php artisan migrate

# 관리자 계정 생성
php artisan make:filament-user

# 프론트엔드 빌드
npm run build

# 서버 실행
php artisan serve
```

**접속**: http://localhost:8000

### Backend API 설치
```bash
cd backend-api

# 가상환경 생성
python -m venv venv
source venv/bin/activate  # Windows: venv\Scripts\activate

# 의존성 설치
pip install -r requirements.txt
playwright install chromium

# 환경 설정
cp .env.example .env

# 서버 실행
uvicorn app.main:app --reload --port 8085
```

**API 문서**: http://localhost:8085/docs

## 주요 API 엔드포인트

```
POST /api/v1/tiktok/scrape                    # 사용자 스크랩
POST /api/v1/tiktok/scrape_video              # 영상 수집
POST /api/v1/tiktok/save_session              # 로그인 세션 저장
POST /api/v1/tiktok/send_message              # DM 발송
POST /api/v1/tiktok/brand/repost-videos       # 브랜드 리포스트 수집
POST /api/v1/tiktok/collect-repost-users      # 원본 사용자 정보 수집
GET  /api/v1/tiktok/                          # 사용자 목록
GET  /docs                                     # Swagger API 문서
```

## 기술적 특징

### 성능 최적화
- 비동기 처리: FastAPI + asyncio로 동시 요청 처리
- 캐싱: Laravel Octane으로 응답 속도 향상
- 인덱싱: 데이터베이스 쿼리 최적화

### 확장성
- 마이크로서비스: Admin Panel과 Backend API 분리
- API 기반: RESTful API로 다른 시스템과 연동 가능
- 모듈화: 서비스 레이어 아키텍처

### 보안
- 환경 변수 기반 설정 관리
- API 키 및 세션 파일 보안 저장
- SQL Injection 방지 (ORM 사용)


## 기술 스택 요약

| 구분 | 기술 |
|------|------|
| Backend | Laravel 11, FastAPI |
| Frontend | Filament v3, Blade, Tailwind CSS |
| Database | MySQL 8.0, SQLAlchemy, Eloquent |
| Automation | Playwright, Playwright-stealth |
| DevOps | Docker, Git |
| Language | PHP 8.2+, Python 3.11+ |

## 프로젝트 구조

```
social-media-platform-portfolio/
├── admin-panel/                    # Laravel + Filament Admin
│   ├── app/
│   │   ├── Filament/              # Filament 리소스 및 페이지
│   │   ├── Models/                # Eloquent 모델
│   │   └── Http/                  # Controllers, Middleware
│   ├── database/
│   │   └── migrations/            # 데이터베이스 마이그레이션
│   ├── resources/
│   │   └── views/                 # Blade 템플릿
│   └── routes/                    # 라우트 정의
│
├── backend-api/                    # FastAPI Backend
│   ├── app/
│   │   ├── api/v1/endpoints/     # API 엔드포인트
│   │   ├── core/                  # 핵심 설정
│   │   ├── models/                # SQLAlchemy 모델
│   │   ├── schemas/               # Pydantic 스키마
│   │   └── services/              # 비즈니스 로직
│   └── requirements.txt           # Python 패키지
│
└── README.md                       # 이 파일
```

## 핵심 역량

- Backend: Laravel, FastAPI, RESTful API 설계
- Frontend: Filament Admin Panel, Blade Templates
- Database: MySQL, ORM 설계 및 최적화
- Automation: Playwright 기반 웹 스크랩
- Architecture: 마이크로서비스, 비동기 프로그래밍
- DevOps: Docker, 환경 설정 관리

