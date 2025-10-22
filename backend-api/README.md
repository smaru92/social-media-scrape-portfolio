# TikTok API Service

FastAPI 기반 TikTok 데이터 수집 및 관리 API 서비스

## 프로젝트 개요

TikTok 사용자 및 영상 데이터 수집, 브랜드 계정 관리, DM 캠페인 관리를 위한 종합 API 플랫폼입니다.
Playwright를 이용한 웹 자동화로 TikTok 데이터를 수집하고, MySQL 데이터베이스에 저장하여 관리합니다.

## 주요 기능

### 1. 사용자 관리
- 키워드 검색을 통한 TikTok 사용자 스크랩
- 팔로워 수 필터링
- 사용자 상태 관리 (미확인, DM전송, DM응답, 폼제출 등)
- 검토 상태 관리 (대기, 승인, 거절)

### 2. 인증 및 세션 관리
- Playwright를 통한 TikTok 로그인 자동화
- 세션 파일 저장 및 재사용
- 관리자 패널에서 세션 파일 업로드

### 3. 영상 스크랩
- 특정 사용자의 영상 수집
- 리포스트 영상 수집 (원본 사용자 정보 포함)
- 영상 메타데이터: 제목, 조회수, 좋아요, 댓글, 공유 수

### 4. 브랜드 계정 관리
- 브랜드 계정별 리포스트 영상 수집
- 리포스트 영상의 원본 사용자 정보 자동 수집
- 브랜드 계정 통계 및 분석

### 5. DM 캠페인
- 메시지 템플릿 관리
- 대량 DM 발송
- 발송 결과 로깅 및 통계

## 기술 스택
- **FastAPI** 0.115.13 - 고성능 비동기 웹 프레임워크
- **MySQL** - 메인 데이터베이스

### 웹 자동화
- **Playwright** - 브라우저 자동화
- **Playwright-stealth** - 탐지 방지
- **Selenium** - 대체 브라우저 자동화


## 프로젝트 구조

```
insta-api/
├── app/
│   ├── main.py                         # FastAPI 애플리케이션 진입점
│   ├── api/
│   │   └── v1/
│   │       ├── router.py              # API 라우터 통합
│   │       └── endpoints/
│   │           ├── tiktok.py          # TikTok API 엔드포인트
│   │           └── tiktok_utils.py    # 엔드포인트 헬퍼 함수
│   ├── core/
│   │   ├── config.py                  # 설정 관리
│   │   └── database.py                # 데이터베이스 설정
│   ├── models/
│   │   └── tiktok.py                  # SQLAlchemy ORM 모델
│   ├── schemas/
│   │   └── tiktok.py                  # Pydantic 스키마
│   └── services/
│       ├── tiktok_service.py          # 핵심 비즈니스 로직
│       ├── browser_manager.py         # Playwright 브라우저 관리
│       ├── tiktok_message_handler.py  # 메시지 템플릿 처리
│       ├── tiktok_db_handler.py       # 데이터베이스 작업
│       ├── tiktok_utils.py            # 유틸리티 함수
│       └── tiktok_exceptions.py       # 커스텀 예외
├── requirements.txt                    # Python 의존성
├── CLAUDE.md                          # 프로젝트 문서
└── tests/                             # 테스트 코드
```

## 설치 및 실행

### 1. 요구사항
- Python 3.11+
- MySQL 8.0+
- Chrome/Chromium (Playwright용)

### 2. 설치
```bash
# 의존성 설치
pip install -r requirements.txt

# Playwright 브라우저 설치
playwright install chromium
```

### 3. 환경 변수 설정
`.env` 파일 생성:
```env
# 데이터베이스
DB_HOST=localhost
DB_PORT=3306
DB_USER=your_user
DB_PASSWORD=your_password
DB_NAME=your_database

# SSH 터널 (선택사항)
SSH_HOST=
SSH_PORT=22
SSH_USERNAME=
SSH_PASSWORD=

# 관리자 URL
ADMIN_URL=http://your-admin-panel.com
```

### 4. 서버 실행
```bash
# 개발 서버 실행
uvicorn app.main:app --reload --port 8085

# 프로덕션 서버 실행
uvicorn app.main:app --host 0.0.0.0 --port 8085
```

### 5. API 문서 확인
- Swagger UI: http://localhost:8085/docs
- ReDoc: http://localhost:8085/redoc

## 주요 API 엔드포인트

### 사용자 관리
- `POST /api/v1/tiktok/scrape` - 사용자 스크랩
- `GET /api/v1/tiktok/` - 사용자 목록 조회

### 인증
- `POST /api/v1/tiktok/save_session` - 로그인 및 세션 저장
- `POST /api/v1/tiktok/upload_session` - 세션 파일 업로드

### 영상 스크랩
- `POST /api/v1/tiktok/scrape_video` - 사용자 영상 수집
- `POST /api/v1/tiktok/scrape_repost_video` - 리포스트 영상 수집

### 브랜드 계정
- `GET /api/v1/tiktok/brand/accounts` - 브랜드 계정 목록
- `POST /api/v1/tiktok/brand/repost-videos` - 리포스트 영상 수집
- `POST /api/v1/tiktok/collect-repost-users` - 원본 사용자 정보 수집

### 메시징
- `POST /api/v1/tiktok/send_message` - DM 발송

## 데이터베이스 모델

### 주요 테이블
1. **tiktok_users** - TikTok 사용자 프로필
   - 상태: unconfirmed, dm_sent, dm_replied, form_submitted 등
   - 검토: pending, approved, rejected

2. **tiktok_videos** - 사용자 영상 메타데이터

3. **tiktok_brand_accounts** - 브랜드 계정 정보

4. **tiktok_repost_videos** - 리포스트 영상 추적
   - `is_checked`: 원본 사용자 정보 수집 여부

5. **tiktok_messages** - DM 캠페인 관리

6. **tiktok_message_templates** - 메시지 템플릿

7. **tiktok_upload_requests** - 업로드 요청 추적

8. **tiktok_message_logs** - DM 발송 로그

## 주요 설계 특징

### 1. 브라우저 관리
- 통합된 `AsyncBrowserManager`와 `SyncBrowserManager` 클래스
- Chrome headless 모드, 탐지 방지 기능 내장
- 표준 User-Agent: Chrome 131.0.0.0
- 뷰포트: 1920x1080 데스크톱

### 2. 서비스 레이어 아키텍처
- `TikTokService`: 메인 스크랩 및 데이터 수집 오케스트레이터
- `TikTokDatabaseHandler`: 데이터베이스 작업 추상화
- `TikTokMessageTemplateManager`: 메시지 템플릿 관리
- `TikTokMessageProcessor`: 메시지 발송 로직

### 3. 유틸리티 모듈
- `TikTokDataParser`: HTML/JSON 파싱
- `TikTokWaitUtils`: 페이지 로드 및 상호작용 대기
- `TikTokImageUtils`: 이미지 다운로드
- `TikTokDatabaseUtils`: 데이터베이스 작업
- `TikTokValidationUtils`: 입력 검증
- `TikTokUrlUtils`: URL 파싱 및 조작

### 4. 예외 처리
- 커스텀 예외 계층 구조
- 특화된 예외: `TikTokCaptchaException`, `TikTokSessionExpiredException`
- `safe_execute` 래퍼로 예외 처리

### 5. 비동기/동기 호환성
- `ThreadPoolExecutor`를 사용한 동기 함수의 비동기 실행
- Windows 호환 이벤트 루프 정책

## 주의사항

### 보안
- API 키나 비밀번호는 환경 변수로 관리
- `.env` 파일 절대 커밋 금지
- 세션 파일 안전하게 보관

### 스크랩 정책
- 영상 목록 스크랩 시 스크롤하지 않음 (탐지 방지)
- Rate limiting 준수
- 로그인 세션 주기적 갱신

### Windows 호환성
- Playwright Windows 이벤트 루프 처리
- 경로 구분자 주의


