"""
TikTok 데이터베이스 작업을 통합 관리하는 헬퍼 클래스
"""
import time
from datetime import datetime
from typing import Dict, List, Optional, Any
from sqlalchemy.orm import Session
from sqlalchemy.exc import SQLAlchemyError

from app.models.tiktok import (
    TikTokBrandAccount, TikTokRepostVideo, TikTokVideo, TikTokUser, 
    TikTokUserLog, TikTokMessage, TikTokUploadRequest
)


class TikTokDatabaseHandler:
    """TikTok 관련 데이터베이스 작업을 통합 관리하는 클래스"""
    
    def __init__(self, db_session: Session):
        self.db_session = db_session
    
    def get_or_create_brand_account(self, username: str) -> TikTokBrandAccount:
        """
        브랜드 계정을 조회하거나 새로 생성합니다.
        
        Args:
            username: 브랜드 계정명
            
        Returns:
            TikTokBrandAccount 인스턴스
        """
        if not self.db_session:
            raise ValueError("Database session is required")
        
        # 기존 브랜드 계정 조회
        brand_account = self.db_session.query(TikTokBrandAccount).filter(
            TikTokBrandAccount.username == username
        ).first()
        
        if not brand_account:
            # 새 브랜드 계정 생성
            brand_account = TikTokBrandAccount(
                username=username,
                brand_name=username,  # 초기값
                created_at=datetime.now(),
                updated_at=datetime.now()
            )
            self.db_session.add(brand_account)
            self.safe_commit()
            print(f"✅ 새 브랜드 계정 생성: {username}")
        
        return brand_account
    
    def upsert_repost_video(self, video_data: Dict, brand_account_id: int) -> Optional[TikTokRepostVideo]:
        """
        리포스트 비디오를 업데이트하거나 새로 생성합니다.
        
        Args:
            video_data: 비디오 데이터
            brand_account_id: 브랜드 계정 ID
            
        Returns:
            TikTokRepostVideo 인스턴스 또는 None
        """
        # 중복 체크
        existing_video = self.db_session.query(TikTokRepostVideo).filter(
            TikTokRepostVideo.tiktok_brand_account_id == brand_account_id,
            TikTokRepostVideo.video_url == video_data['video_url']
        ).first()
        
        repost_record = None
        if existing_video:
            # 동시성 문제 해결을 위한 재시도 로직
            max_retries = 3
            for attempt in range(max_retries):
                try:
                    # 세션 새로고침으로 최신 데이터 가져오기
                    self.db_session.refresh(existing_video)
                    
                    # 기존 리포스트 비디오 정보 업데이트
                    existing_video.title = video_data['title']
                    existing_video.view_count = video_data['view_count']
                    existing_video.updated_at = datetime.now()
                    
                    # 즉시 커밋하여 락 해제
                    self.safe_commit()
                    repost_record = existing_video
                    print(f"🔄 기존 리포스트 비디오 업데이트: {video_data['video_url'][:50]}...")
                    break
                    
                except Exception as retry_error:
                    self.safe_rollback()
                    if attempt < max_retries - 1:
                        print(f"⚠️ 업데이트 재시도 {attempt + 1}/{max_retries}: {retry_error}")
                        time.sleep(0.1 * (attempt + 1))  # 지수 백오프
                        # 레코드 다시 조회
                        existing_video = self.db_session.query(TikTokRepostVideo).filter(
                            TikTokRepostVideo.tiktok_brand_account_id == brand_account_id,
                            TikTokRepostVideo.video_url == video_data['video_url']
                        ).first()
                        if not existing_video:
                            print(f"⚠️ 레코드가 삭제됨, 새로 생성합니다.")
                            break
                    else:
                        print(f"❌ 최대 재시도 횟수 초과: {retry_error}")
                        raise retry_error
        
        if not repost_record:
            # 새 리포스트 비디오 생성 및 저장
            repost_video = TikTokRepostVideo.from_scrape_data(video_data, brand_account_id)
            self.db_session.add(repost_video)
            self.safe_commit()  # 즉시 커밋
            repost_record = repost_video
            print(f"✅ 새 리포스트 비디오 추가: {video_data['video_url'][:50]}...")
        
        return repost_record
    
    def upsert_video(self, video_data: Dict, tiktok_user_id: int) -> Optional[TikTokVideo]:
        """
        비디오를 업데이트하거나 새로 생성합니다.
        
        Args:
            video_data: 비디오 데이터  
            tiktok_user_id: TikTok 사용자 ID
            
        Returns:
            TikTokVideo 인스턴스 또는 None
        """
        # 중복 체크 (같은 video_url이 이미 있는지 확인)
        existing_video = self.db_session.query(TikTokVideo).filter(
            TikTokVideo.tiktok_user_id == tiktok_user_id,
            TikTokVideo.video_url == video_data['link']
        ).first()
        
        video_record = None
        if existing_video:
            # 기존 비디오 정보 업데이트
            existing_video.title = video_data['alt']
            existing_video.view_count = video_data['views']
            video_record = existing_video
            print(f"🔄 기존 비디오 업데이트: {video_data['link'][:50]}...")
        else:
            # 새 비디오 생성 및 저장
            video = TikTokVideo.from_scrape_data(video_data, tiktok_user_id)
            self.db_session.add(video)
            self.db_session.flush()  # ID 생성을 위해 flush
            video_record = video
            print(f"✅ 새 비디오 추가: {video_data['link'][:50]}...")
        
        return video_record
    
    def get_user_by_username(self, username: str) -> Optional[TikTokUser]:
        """사용자명으로 TikTok 사용자 조회"""
        return self.db_session.query(TikTokUser).filter(
            TikTokUser.username == username
        ).first()
    
    def get_videos_by_user_id(self, user_id: int) -> List[TikTokVideo]:
        """사용자 ID로 해당 사용자의 모든 비디오 조회"""
        return self.db_session.query(TikTokVideo).filter(
            TikTokVideo.tiktok_user_id == user_id
        ).all()
    
    def get_repost_video_by_url(self, brand_account_id: int, video_url: str) -> Optional[TikTokRepostVideo]:
        """브랜드 계정과 URL로 리포스트 비디오 조회"""
        return self.db_session.query(TikTokRepostVideo).filter(
            TikTokRepostVideo.tiktok_brand_account_id == brand_account_id,
            TikTokRepostVideo.video_url == video_url
        ).first()
    
    def update_user_log(self, log_id: int, update_data: Dict) -> bool:
        """
        TikTok 사용자 수집 로그 업데이트
        
        Args:
            log_id: 로그 ID
            update_data: 업데이트할 데이터
            
        Returns:
            성공 여부
        """
        try:
            log = self.db_session.query(TikTokUserLog).filter(
                TikTokUserLog.id == log_id
            ).first()
            
            if log:
                for key, value in update_data.items():
                    if hasattr(log, key):
                        setattr(log, key, value)
                self.safe_commit()
                print(f"[SUCCESS] 로그 업데이트 완료 (ID: {log_id}, 탐지: {update_data.get('search_user_count')}, 저장: {update_data.get('save_user_count')})")
                return True
        except Exception as e:
            print(f"❗로그 업데이트 오류: {e}")
            self.safe_rollback()
            return False
    
    def update_user_profile_image(self, username: str, uploaded_url: str) -> bool:
        """
        사용자 프로필 이미지 URL 업데이트
        
        Args:
            username: 사용자명
            uploaded_url: 업로드된 이미지 URL
            
        Returns:
            성공 여부
        """
        try:
            user_record = self.db_session.query(TikTokUser).filter(
                TikTokUser.username == username
            ).first()
            
            if user_record:
                user_record.profile_image = uploaded_url
                self.safe_commit()
                print(f"🖼️ 프로필 이미지 URL 업데이트: user ID {user_record.id}, URL: {uploaded_url}")
                return True
            return False
        except Exception as e:
            print(f"❗ 프로필 이미지 URL 업데이트 실패: {e}")
            self.safe_rollback()
            return False
    
    def update_video_thumbnail(self, video_record: TikTokVideo, uploaded_url: str) -> bool:
        """
        비디오 썸네일 URL 업데이트
        
        Args:
            video_record: 비디오 레코드
            uploaded_url: 업로드된 썸네일 URL
            
        Returns:
            성공 여부
        """
        try:
            video_record.thumbnail_url = uploaded_url
            self.safe_commit()
            print(f"🖼️ 썸네일 URL 업데이트: video ID {video_record.id}")
            return True
        except Exception as e:
            print(f"❗ 썸네일 URL 업데이트 실패: {e}")
            self.safe_rollback()
            return False
    
    def update_repost_video_thumbnail(self, repost_record: TikTokRepostVideo, uploaded_url: str) -> bool:
        """
        리포스트 비디오 썸네일 URL 업데이트
        
        Args:
            repost_record: 리포스트 비디오 레코드
            uploaded_url: 업로드된 썸네일 URL
            
        Returns:
            성공 여부
        """
        try:
            repost_record.thumbnail_url = uploaded_url
            self.safe_commit()
            print(f"🖼️ 리포스트 썸네일 URL 업데이트: repost video ID {repost_record.id}")
            return True
        except Exception as e:
            print(f"❗ 리포스트 썸네일 URL 업데이트 실패: {e}")
            self.safe_rollback()
            return False
    
    def update_upload_request(self, request: TikTokUploadRequest, matched_video: TikTokVideo) -> bool:
        """
        업로드 요청 정보 업데이트
        
        Args:
            request: 업로드 요청 레코드
            matched_video: 매칭된 비디오 레코드
            
        Returns:
            성공 여부
        """
        try:
            request.is_uploaded = True
            request.upload_url = matched_video.video_url
            request.upload_thumbnail_url = matched_video.thumbnail_url
            request.uploaded_at = matched_video.posted_at
            request.tiktok_video_id = matched_video.id
            
            self.safe_commit()
            return True
        except Exception as e:
            print(f"❗ 업로드 요청 업데이트 실패: {e}")
            self.safe_rollback()
            return False
    
    def update_message_status(self, message_id: int, status: str, **kwargs) -> bool:
        """
        메시지 상태 업데이트
        
        Args:
            message_id: 메시지 ID
            status: 상태 (pending, sending, completed)
            **kwargs: 추가 업데이트 필드
            
        Returns:
            성공 여부
        """
        try:
            message_record = self.db_session.query(TikTokMessage).filter(
                TikTokMessage.id == message_id
            ).first()
            
            if message_record:
                message_record.send_status = status
                for key, value in kwargs.items():
                    if hasattr(message_record, key):
                        setattr(message_record, key, value)
                
                self.safe_commit()
                return True
            return False
        except Exception as e:
            print(f"❗ 메시지 상태 업데이트 실패: {e}")
            self.safe_rollback()
            return False
    
    def safe_commit(self) -> bool:
        """
        안전한 커밋 실행
        
        Returns:
            성공 여부
        """
        try:
            self.db_session.commit()
            return True
        except SQLAlchemyError as e:
            print(f"❗ 커밋 실패: {e}")
            self.safe_rollback()
            return False
    
    def safe_rollback(self) -> None:
        """안전한 롤백 실행"""
        try:
            self.db_session.rollback()
        except SQLAlchemyError as e:
            print(f"❗ 롤백 실패: {e}")
    
    def safe_add(self, instance: Any) -> bool:
        """
        안전한 객체 추가
        
        Args:
            instance: 추가할 객체
            
        Returns:
            성공 여부
        """
        try:
            self.db_session.add(instance)
            return True
        except SQLAlchemyError as e:
            print(f"❗ 객체 추가 실패: {e}")
            return False
    
    def safe_flush(self) -> bool:
        """
        안전한 플러시 실행
        
        Returns:
            성공 여부
        """
        try:
            self.db_session.flush()
            return True
        except SQLAlchemyError as e:
            print(f"❗ 플러시 실패: {e}")
            self.safe_rollback()
            return False