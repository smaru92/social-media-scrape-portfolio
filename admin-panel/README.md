# TikTok/Instagram 메시지 관리 시스템

Laravel + Filament Admin Panel을 사용한 TikTok/Instagram 인플루언서 관리 및 메시지 자동화 시스템

## 기술 스택

- **Backend**: Laravel 11
- **Admin Panel**: Filament v3
- **Database**: MySQL
- **Frontend**: Blade Templates + Tailwind CSS
- **Excel Processing**: Maatwebsite Excel, PHPSpreadsheet
- **Performance**: Laravel Octane

## 주요 기능

### 1. 틱톡 사용자 관리
- 사용자 정보 수집 및 관리 (username, nickname, followers, bio 등)
- 개인정보 수집 동의 관리 (is_collaborator)
- 사용자 상태 추적 (미확인 → DM전송 → DM답변 → 구글폼제출 → 영상업로드대기 → 완료)
- 국가 정보 관리 (ISO 3166-1 alpha-2)
- 엑셀 일괄 업로드/다운로드
- 분류1, 분류2, 리포스트 허가 필터링

### 2. 메시지 시스템
- 메시지 템플릿 관리
- 자동 메시지 발송 스케줄링
- 발송 로그 추적 및 결과 확인
- 발신자별 메시지 관리

### 3. 업로드 요청 관리
- 캠페인별 업로드 요청 생성
- 필수 태그 및 기한 설정
- 업로드 상태 추적 (대기 → 진행중 → 완료 → 만료)
- 확인 처리 기능

### 4. 동영상 관리
- TikTok 동영상 정보 스크랩
- 리포스트 영상 추적 및 확인
- 동영상 통계 관리
- API 연동을 통한 자동 스크랩

### 5. 개인정보 관리
- 사용자 개인정보 수집
- 엑셀 일괄 등록 기능
- TikTok username 기반 자동 매칭
- 중복 방지 처리

## 데이터베이스 구조

### 주요 테이블

#### tiktok_users
- 사용자 기본 정보 (username, nickname, followers, bio)
- 협업 상태 관리 (is_collaborator, collaborated_at)
- 진행 상태 추적 (status)
- 국가 정보 (country)

#### tiktok_messages
- 메시지 발송 관리
- 템플릿 연결
- 자동 발송 설정
- 발송 시간 관리

#### tiktok_message_logs
- 메시지 발송 로그
- 전송 결과 기록
- 발신자/수신자 추적

#### tiktok_repost_videos
- 리포스트 영상 정보
- 확인 여부 추적 (is_checked)

## 설치 및 실행

### 요구사항
- PHP 8.2 이상
- Composer
- MySQL
- Node.js & NPM

### 설치 방법

```bash
# 의존성 설치
composer install
npm install

# 환경 설정
cp .env.example .env
php artisan key:generate

# 데이터베이스 마이그레이션
php artisan migrate

# Filament 최적화
php artisan filament:optimize

# 프론트엔드 빌드
npm run build
```

## API 연동

### 동영상 스크랩
- Endpoint: `{API_URL}/api/v1/tiktok/scrape_video`
- 업로드 완료 및 기한 만료 요청 자동 제외
- BulkAction 및 HeaderAction 지원

## 개발 주의사항

### Filament
- BulkAction은 Collection 파라미터로 레코드 처리
- ListRecords 페이지에서 `getTableSelectedRecords()` 사용 불가
- 처리 후 `deselectRecordsAfterCompletion()` 호출 필요

### Laravel
- 마이그레이션 시 기본값 설정 및 외래키 확인
- 모델의 `$fillable` 배열 확인
- 관계(Relationship) 설정 확인

### 문서화 정책
중요한 기능 추가나 구조 변경 시에만 CLAUDE.md에 기록:
- 새로운 주요 기능 구현
- 시스템 구조 변경
- API 연동 추가
- 중요한 버그 수정

## 프로젝트 구조

```
app/
├── Filament/          # Filament Admin 리소스
├── Models/            # Eloquent 모델
└── Http/              # Controllers, Middleware

database/
├── migrations/        # 데이터베이스 마이그레이션
└── seeders/          # 시더 파일

resources/
├── views/            # Blade 템플릿
└── css/              # 스타일시트

public/
├── css/              # 컴파일된 CSS
└── js/               # 컴파일된 JS
```

## 라이선스

MIT License

## 지원

프로젝트 관련 문의사항이 있으시면 이슈를 등록해주세요.
