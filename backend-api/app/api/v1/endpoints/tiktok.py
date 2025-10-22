from fastapi import APIRouter, Depends, HTTPException, Form
from app.schemas.tiktok import ScrapeRequest, TikTokLoginRequest, SendMessageRequest, UploadSessionRequest, ScrapeVideoRequest, CollectRepostUsersRequest
from app.services.tiktok_service import TikTokService
from app.core.database import get_sync_db
from app.utils.endpoint_helpers import (
    execute_tiktok_service, get_session_file_path, handle_endpoint_error,
    create_success_response, validate_session_file, TikTokEndpointHelper
)
from app.models.tiktok import (
    TikTokRepostVideo, TikTokUser, TikTokSender,
    TikTokBrandAccount, TikTokUploadRequest, TikTokVideo
)
from sqlalchemy.orm import Session
from sqlalchemy import or_
from datetime import datetime, timedelta
import asyncio
from concurrent.futures import ThreadPoolExecutor
import json
import os
import requests
from typing import Optional

router = APIRouter()

# === USER MANAGEMENT ===

@router.post("/scrape")
async def scrape_users(request: ScrapeRequest, db: Session = Depends(get_sync_db)):
    try:
        print(f"Request received: {request}")
        
        result = await execute_tiktok_service(
            db,
            'scrape_users',
            request.keyword,
            request.min_followers,
            request.scrolls,
            True,
            request.tiktok_user_log_id
        )
        
        return result
    except Exception as e:
        return handle_endpoint_error(e, "scrape_users")


# === AUTHENTICATION & SESSION ===

# 테스트 해본결과 실제 로그인시에는 가상브라우저 사용시 매우 높은확률로 캡챠가 나오기 때문에 API를 사용하지않고 직접하는게 좋아보임
@router.post("/save_session")
async def save_tiktok_login_session(request: TikTokLoginRequest, db: Session = Depends(get_sync_db)):
    try:
        print(f"Login request received for user: {request.username}")
        
        result = await execute_tiktok_service(
            db,
            'login_with_playwright',
            request.username,
            request.password,
            None  # session_file_name은 함수 내부에서 자동 생성
        )
        
        return result
    except Exception as e:
        return handle_endpoint_error(e, "save_tiktok_login_session")


@router.post("/upload_session")
async def upload_session(request: UploadSessionRequest):
    """
    관리자 페이지에서 세션 파일을 API 서버로 업로드
    보안을 위해 로컬 서버에만 파일 저장
    """
    try:
        print(f"Uploading session file for sender {request.sender_id}")
        
        # 세션 파일을 로컬 디렉토리에 저장
        session_dir = "tiktok_sessions"
        os.makedirs(session_dir, exist_ok=True)
        
        file_path = os.path.join(session_dir, request.file_name)
        
        # 세션 데이터를 JSON 파일로 저장
        with open(file_path, 'w', encoding='utf-8') as f:
            json.dump(request.session_data, f, ensure_ascii=False, indent=2)
        
        # 파일 권한 설정 (보안 강화)
        os.chmod(file_path, 0o600)  # 소유자만 읽기/쓰기 가능
        
        return create_success_response({
            "file_path": file_path,
            "sender_id": request.sender_id,
            "file_size": os.path.getsize(file_path)
        }, "Session file uploaded successfully")
        
    except Exception as e:
        return handle_endpoint_error(e, "upload_session")


# === VIDEO SCRAPING ===

@router.post("/scrape_video")
async def scrape_videos(request: ScrapeVideoRequest, db: Session = Depends(get_sync_db)):
    """
    TikTok 사용자들의 비디오 정보를 스크래핑합니다.
    
    Args:
        request: 사용자명 리스트, 세션 사용 여부, 세션 파일 경로, sender_id
        db: 데이터베이스 세션
        
    Returns:
        스크래핑 결과
    """
    try:
        print(f"Scrape video request received for {len(request.usernames)} users")
        print(f"Use session: {request.use_session}")
        print(f"Sender ID: {request.sender_id}")
        
        # 비디오 스크래핑 파라미터 준비 (세션 파일 포함)
        params = TikTokEndpointHelper.prepare_video_scraping_params(request, db)
        print(f"Final session file: {params['session_file']}")
        
        result = await execute_tiktok_service(
            db,
            'scrape_user_videos',
            params['usernames'],
            params['use_session'],
            params['session_file']
        )
        
        return result
    except Exception as e:
        return handle_endpoint_error(e, "scrape_videos")


@router.post("/scrape_repost_video")
async def scrape_repost_videos(request: ScrapeVideoRequest, db: Session = Depends(get_sync_db)):
    """
    TikTok 사용자들의 리포스트 비디오 정보를 스크래핑합니다.
    
    Args:
        request: 사용자명 리스트, 세션 사용 여부, 세션 파일 경로, sender_id
        db: 데이터베이스 세션
        
    Returns:
        스크래핑 결과
    """
    try:
        print(f"Scrape repost video request received for {len(request.usernames)} users")
        print(f"Use session: {request.use_session}")
        
        # sender_id가 있으면 DB에서 해당 세션 파일 경로 조회
        session_file_path = None
        if request.sender_id:
            sender = db.query(TikTokSender).filter(TikTokSender.id == request.sender_id).first()
            if sender and sender.session_file_path:
                session_file_path = sender.session_file_path
                print(f"Using session file from DB for sender {request.sender_id}: {session_file_path}")
        
        # request에서 직접 전달된 세션 파일 경로가 있으면 우선 사용
        if request.session_file:
            session_file_path = request.session_file
            print(f"Using session file from request: {session_file_path}")
        
        # 서비스 초기화 및 실행
        scraper = TikTokService(db_session=db)
        
        # Windows 호환성을 위해 동기 함수를 ThreadPoolExecutor에서 실행
        loop = asyncio.get_event_loop()
        with ThreadPoolExecutor() as executor:
            result = await loop.run_in_executor(
                executor,
                scraper.scrape_user_repost_videos,
                request.usernames,
                request.use_session,
                session_file_path if session_file_path else "tiktok_auth.json"
            )
        
        # 응답에 타임스탬프 추가
        result["timestamp"] = datetime.now().isoformat()
        result["request_info"] = {
            "total_users": len(request.usernames),
            "use_session": request.use_session,
            "sender_id": request.sender_id
        }

        # 리포스트 수집이 완료되면 관리페이지에 콜백
        try:
            admin_url = os.getenv("ADMIN_URL", "https://example.com")
            callback_url = f"{admin_url}/api/tiktok/callback-collect-repost-users"
            callback_data = {"limit": 100}

            import requests
            response = requests.post(callback_url, json=callback_data, timeout=10)

            if response.status_code == 200:
                print(f"✅ 관리페이지 콜백 성공: {callback_url}")
                result["callback_status"] = "success"
            else:
                print(f"⚠️ 관리페이지 콜백 실패: {response.status_code}")
                result["callback_status"] = f"failed: {response.status_code}"

        except Exception as callback_error:
            print(f"❌ 관리페이지 콜백 오류: {callback_error}")
            result["callback_status"] = f"error: {str(callback_error)}"

        return result
    except Exception as e:
        print(f"Error in scrape_repost_videos: {e}")
        import traceback
        traceback.print_exc()
        return {"error": str(e), "message": "Internal server error"}


# === BRAND MANAGEMENT ===

@router.post("/brand/repost-videos")
async def scrape_brand_repost_videos(
    request: ScrapeVideoRequest,
    db: Session = Depends(get_sync_db)
):
    """
    브랜드 계정들의 리포스트 비디오를 수집합니다.
    
    Args:
        request: ScrapeVideoRequest (usernames 배열 포함)
    
    Returns:
        수집 결과 (브랜드 계정들 정보, 리포스트 비디오 목록, 통계)
    """
    try:
        print(f"Request received: {request}")
        
        tiktok_service = TikTokService(db_session=db)
        all_results = []
        total_stats = {
            "total_videos": 0,
            "new_videos": 0,
            "updated_videos": 0,
            "errors": 0
        }
        
        # 각 브랜드 계정에 대해 수집
        for brand_username in request.usernames:
            print(f"Processing brand account: {brand_username}")
            
            # Windows 호환성을 위해 동기 함수를 ThreadPoolExecutor에서 실행
            loop = asyncio.get_event_loop()
            with ThreadPoolExecutor() as executor:
                brand_result = await loop.run_in_executor(
                    executor,
                    tiktok_service.scrape_brand_repost_videos,
                    brand_username,
                    request.max_videos or 20,
                    request.use_session or False,
                    request.session_file or "tiktok_auth.json"
                )
            
            all_results.append(brand_result)
            
            # 통계 합산
            if "stats" in brand_result:
                for key in total_stats:
                    total_stats[key] += brand_result["stats"].get(key, 0)
        
        result = {
            "results": all_results,
            "total_stats": total_stats,
            "processed_accounts": len(request.usernames)
        }
        
        return result
        
    except Exception as e:
        print(f"Error in scrape_brand_repost_videos: {e}")
        import traceback
        traceback.print_exc()
        return {"error": str(e), "message": "Internal server error"}


@router.get("/brand/accounts")
async def get_brand_accounts(
    skip: int = 0,
    limit: int = 100,
    db: Session = Depends(get_sync_db)
):
    """
    등록된 브랜드 계정 목록을 조회합니다.
    
    Args:
        skip: 건너뛸 레코드 수
        limit: 조회할 최대 레코드 수
    
    Returns:
        브랜드 계정 목록
    """
    try:
        accounts = db.query(TikTokBrandAccount).offset(skip).limit(limit).all()
        return {
            "accounts": [account.to_dict() for account in accounts],
            "total": db.query(TikTokBrandAccount).count()
        }
    except Exception as e:
        print(f"Error in get_brand_accounts: {e}")
        return {"error": str(e), "message": "Internal server error"}


@router.get("/brand/{brand_id}/repost-videos")
async def get_brand_repost_videos(
    brand_id: int,
    skip: int = 0,
    limit: int = 100,
    db: Session = Depends(get_sync_db)
):
    """
    특정 브랜드 계정의 리포스트 비디오 목록을 조회합니다.

    Args:
        brand_id: 브랜드 계정 ID
        skip: 건너뛸 레코드 수
        limit: 조회할 최대 레코드 수

    Returns:
        리포스트 비디오 목록
    """
    try:
        videos = db.query(TikTokRepostVideo).filter(
            TikTokRepostVideo.tiktok_brand_account_id == brand_id
        ).order_by(
            TikTokRepostVideo.posted_at.desc()
        ).offset(skip).limit(limit).all()

        return {
            "videos": [video.to_dict() for video in videos],
            "total": db.query(TikTokRepostVideo).filter(
                TikTokRepostVideo.tiktok_brand_account_id == brand_id
            ).count()
        }
    except Exception as e:
        print(f"Error in get_brand_repost_videos: {e}")
        return {"error": str(e), "message": "Internal server error"}


@router.post("/collect-repost-users")
async def collect_repost_users(
    request: CollectRepostUsersRequest,
    db: Session = Depends(get_sync_db)
):
    """
    미확인 리포스트 비디오의 원본 사용자 정보를 수집합니다.

    Args:
        request: CollectRepostUsersRequest (limit, user_agent, session_file 포함)
        db: 데이터베이스 세션

    Returns:
        처리 결과
    """
    try:
        # 미확인 리포스트 비디오 조회
        unchecked_videos = db.query(TikTokRepostVideo).filter(
            TikTokRepostVideo.is_checked == 'N'
        ).limit(request.limit).all()

        if not unchecked_videos:
            return {
                "message": "No unchecked videos found",
                "processed": 0
            }

        tiktok_service = TikTokService(db_session=db)

        # 비디오 데이터 준비 (브랜드 계정 정보 포함)
        video_data_list = []
        for video in unchecked_videos:
            # 브랜드 계정의 country 값 조회
            brand_account = db.query(TikTokBrandAccount).filter(
                TikTokBrandAccount.id == video.tiktok_brand_account_id
            ).first()

            video_data_list.append({
                "video_url": video.video_url,
                "video_id": video.id,
                "country": brand_account.country if brand_account else None
            })

        # 브라우저를 재사용하여 여러 사용자 정보 수집
        result = await execute_tiktok_service(
            db,
            'collect_multiple_users_from_videos',
            video_data_list,
            request.user_agent,  # user_agent 파라미터 추가
            request.session_file  # session_file 파라미터 추가
        )

        processed_count = result.get('processed', 0)
        collected_users = result.get('collected_users', [])
        failed_videos = result.get('failed_videos', [])

        return {
            "message": "User collection completed",
            "processed": processed_count,
            "collected_users": collected_users,
            "failed_videos": failed_videos,
            "total_unchecked": len(unchecked_videos)
        }

    except Exception as e:
        print(f"Error in collect_repost_users: {e}")
        import traceback
        traceback.print_exc()
        return {"error": str(e), "message": "Internal server error"}


# === MESSAGING ===

@router.post("/send_message")
async def send_message(request: SendMessageRequest, db: Session = Depends(get_sync_db)):
    try:
        print(f"Send message request received for {len(request.usernames)} users")
        print(f"Template code: {request.template_code}")
        print(f"Session file path: {request.session_file_path}")
        print(f"Message ID: {request.message_id}")
        
        tiktok_service = TikTokService(db_session=db)
        
        # 세션 파일 경로 확인
        session_file = request.session_file_path
        
        # 서버에 해당 파일이 있는지 확인
        if not os.path.exists(session_file):
            raise HTTPException(
                status_code=404,
                detail=f"Session file not found: {session_file}. Please make sure the file exists on the server."
            )
        
        print(f"Using session file: {session_file}")
        # Windows 호환성을 위해 동기 함수를 ThreadPoolExecutor에서 실행
        loop = asyncio.get_event_loop()
        with ThreadPoolExecutor() as executor:
            # 여러 사용자에게 메시지 일괄 전송 (각 사용자마다 템플릿에서 랜덤 메시지 생성)
            try:
                result = await loop.run_in_executor(
                    executor,
                    tiktok_service.send_bulk_tiktok_messages,
                    request.usernames,
                    session_file,
                    request.template_code,
                    request.message_id  # message_id 전달
                )
            except ValueError as e:
                # 템플릿이 없는 경우 404 에러
                raise HTTPException(status_code=404, detail=str(e))
        
        return {
            "success": True,
            "usernames_count": len(request.usernames),
            "template_code": request.template_code,
            "session_file_path": request.session_file_path,
            "message_id": request.message_id,
            "result": result
        }
    except Exception as e:
        print(f"Error in send_message: {e}")
        import traceback
        traceback.print_exc()
        
        return {"error": str(e), "message": "Internal server error"}


# === UPLOAD MANAGEMENT ===

@router.post("/upload_check")
async def upload_check(db: Session = Depends(get_sync_db)):
    """
    업로드 요청을 확인하고 매칭되는 비디오를 찾아 정보를 업데이트합니다.
    
    1. is_uploaded=0이고 deadline_date가 지나지 않은 tiktok_upload_requests 조회
    2. 각 요청에 대해 해당 사용자의 tiktok_videos 조회
    3. request_tags가 title에 포함된 비디오 찾기
    4. 매칭된 비디오의 상세 정보 스크래핑 및 업데이트
    """
    try:
        # is_uploaded=0이고 deadline_date가 아직 지나지 않은 요청들 조회
        # deadline_date가 NULL이거나 현재 시간보다 미래인 경우만 조회
        current_time = datetime.now()
        pending_requests = db.query(TikTokUploadRequest).filter(
            TikTokUploadRequest.is_uploaded == False,
            TikTokUploadRequest.is_confirm == False,
            or_(
                TikTokUploadRequest.deadline_date.is_(None),
                TikTokUploadRequest.deadline_date >= current_time
            )
        ).all()
        
        if not pending_requests:
            return {
                "message": "No pending upload requests found",
                "checked_count": 0,
                "updated_count": 0
            }
        
        print(f"Found {len(pending_requests)} pending upload requests (deadline not expired)")
        
        tiktok_service = TikTokService(db_session=db)
        
        # Windows 호환성을 위해 동기 함수를 ThreadPoolExecutor에서 실행
        loop = asyncio.get_event_loop()
        with ThreadPoolExecutor() as executor:
            result = await loop.run_in_executor(
                executor,
                tiktok_service.check_and_update_uploads,
                pending_requests
            )
        
        return result
        
    except Exception as e:
        print(f"Error in upload_check: {e}")
        import traceback
        traceback.print_exc()
        return {"error": str(e), "message": "Internal server error"}