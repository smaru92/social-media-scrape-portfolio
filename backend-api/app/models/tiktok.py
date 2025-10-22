from datetime import datetime
from typing import Optional, List, Dict
from sqlalchemy import Column, BigInteger, String, Integer, Text, TIMESTAMP, select, update, and_, Boolean, Enum, JSON
from sqlalchemy.orm import Session
from sqlalchemy.sql import func
from app.core.database import Base
import enum


class TikTokUser(Base):
    """TikTok 사용자 정보 모델"""

    __tablename__ = 'tiktok_users'

    id = Column(BigInteger, primary_key=True, autoincrement=True)
    username = Column(String(255), nullable=True, comment='계정명')
    is_collaborator = Column(Boolean, nullable=False, default=False, comment='개인정보 수집 동의 여부')
    collaborated_at = Column(TIMESTAMP, nullable=True, comment='동의 일시')
    keyword = Column(String(255), nullable=True, comment='검색시 사용한 키워드')
    nickname = Column(Text, nullable=True, comment='사용자 닉네임, 간단소개')
    profile_image = Column(Text, nullable=True, comment='프로필 이미지 URL')
    followers = Column(Integer, nullable=True, comment='팔로워 수')
    profile_url = Column(Text, nullable=True, comment='주소')
    bio = Column(Text, nullable=True, comment='자기소개')
    memo = Column(Text, nullable=True, comment='비고')
    created_at = Column(TIMESTAMP, server_default=func.now(), nullable=True)
    updated_at = Column(TIMESTAMP, server_default=func.now(), onupdate=func.now(), nullable=True)
    deleted_at = Column(TIMESTAMP, nullable=True)
    status = Column(String(50), nullable=False, server_default='unconfirmed', comment='진행상태: unconfirmed(미확인), dm_sent(DM전송완료), dm_replied(DM답변완료), form_submitted(구글폼제출완료), upload_waiting(영상업로드대기), upload_completed(영상업로드완료)')
    country = Column(String(2), nullable=True, comment='국가코드 (ISO 3166-1 alpha-2)')
    review_status = Column(Enum('pending', 'approved', 'rejected'), nullable=False, server_default='pending', comment='심사 상태: pending(대기), approved(승인), rejected(탈락)')
    review_score = Column(Integer, nullable=True, comment='심사 점수')
    review_comment = Column(Text, nullable=True, comment='심사 코멘트')
    reviewed_at = Column(TIMESTAMP, nullable=True, comment='심사 일시')
    reviewed_by = Column(BigInteger, nullable=True, comment='심사자 ID')

    def __repr__(self):
        return f"<TikTokUser(username='{self.username}', followers={self.followers})>"

    def to_dict(self):
        """모델을 딕셔너리로 변환"""
        return {
            'id': self.id,
            'username': self.username,
            'is_collaborator': self.is_collaborator,
            'collaborated_at': self.collaborated_at.isoformat() if self.collaborated_at else None,
            'keyword': self.keyword,
            'nickname': self.nickname,
            'profile_image': self.profile_image,
            'followers': self.followers,
            'profile_url': self.profile_url,
            'bio': self.bio,
            'memo': self.memo,
            'created_at': self.created_at.isoformat() if self.created_at else None,
            'updated_at': self.updated_at.isoformat() if self.updated_at else None,
            'deleted_at': self.deleted_at.isoformat() if self.deleted_at else None,
            'status': self.status,
            'country': self.country,
            'review_status': self.review_status,
            'review_score': self.review_score,
            'review_comment': self.review_comment,
            'reviewed_at': self.reviewed_at.isoformat() if self.reviewed_at else None,
            'reviewed_by': self.reviewed_by
        }

    @classmethod
    def from_scrape_data(cls, data: dict):
        """스크래핑 데이터로부터 모델 인스턴스 생성"""
        return cls(
            username=data.get('username'),
            keyword=data.get('keyword'),
            nickname=data.get('nickname'),
            followers=data.get('followers'),
            profile_url=data.get('profile_url'),
            profile_image=data.get('profile_image'),
            bio=data.get('bio'),
            country=data.get('country'),
            created_at=datetime.now(),
            updated_at=datetime.now()
        )


class TikTokUserLog(Base):
    """TikTok 사용자 수집 로그 모델"""

    __tablename__ = 'tiktok_user_logs'

    id = Column(BigInteger, primary_key=True, autoincrement=True)
    keyword = Column(String(255), nullable=True, default='', comment='검색시 사용한 키워드')
    min_followers = Column(Integer, nullable=True, default=0, comment='검색시 최소 팔로워 수 조건')
    search_user_count = Column(Integer, nullable=True, default=0, comment='탐지한 유저 수')
    save_user_count = Column(Integer, nullable=True, default=0, comment='저장한 유저 수')
    is_error = Column(Boolean, nullable=True, default=False, comment='에러발생 여부')
    created_at = Column(TIMESTAMP, server_default=func.now(), nullable=True)
    updated_at = Column(TIMESTAMP, server_default=func.now(), onupdate=func.now(), nullable=True)

    def __repr__(self):
        return f"<TikTokUserLog(id={self.id}, keyword='{self.keyword}', search_count={self.search_user_count}, save_count={self.save_user_count})>"


class TikTokUserRepository:
    """TikTok 사용자 데이터 동기 저장소"""

    def __init__(self, db_session: Session):
        self.session = db_session

    def create(self, user_data: dict) -> TikTokUser:
        """새 사용자 생성"""
        user = TikTokUser.from_scrape_data(user_data)
        self.session.add(user)
        self.session.commit()
        self.session.refresh(user)
        return user

    def get_by_username(self, username: str) -> Optional[TikTokUser]:
        """username으로 사용자 조회"""
        return self.session.query(TikTokUser).filter(
            and_(
                TikTokUser.username == username,
                TikTokUser.deleted_at.is_(None)
            )
        ).first()

    def exists(self, username: str) -> bool:
        """username으로 사용자 존재 여부 확인"""
        return self.session.query(TikTokUser).filter(
            and_(
                TikTokUser.username == username,
                TikTokUser.deleted_at.is_(None)
            )
        ).first() is not None

    def update(self, user_id: int, update_data: dict) -> Optional[TikTokUser]:
        """사용자 정보 업데이트"""
        user = self.session.query(TikTokUser).filter(
            and_(
                TikTokUser.id == user_id,
                TikTokUser.deleted_at.is_(None)
            )
        ).first()

        if user:
            for key, value in update_data.items():
                if hasattr(user, key):
                    setattr(user, key, value)
            self.session.commit()
            self.session.refresh(user)

        return user

    def upsert_from_scrape(self, users_data: List[Dict]) -> Dict:
        """스크래핑 데이터 upsert (있으면 업데이트, 없으면 생성)

        Returns:
            처리 결과 통계
        """
        stats = {
            'created': 0,
            'updated': 0,
            'skipped': 0
        }

        for data in users_data:
            username = data.get('username')
            if not username:
                stats['skipped'] += 1
                continue

            existing_user = self.get_by_username(username)

            if existing_user:
                # 팔로워 수가 변경된 경우만 업데이트
                if existing_user.followers != data.get('followers'):
                    self.update(existing_user.id, {
                        'followers': data.get('followers'),
                        'bio': data.get('bio'),
                        'nickname': data.get('nickname'),
                        'profile_image': data.get('profile_image'),
                        'country': data.get('country'),
                        'updated_at': datetime.now()
                    })
                    stats['updated'] += 1
                else:
                    stats['skipped'] += 1
            else:
                self.create(data)
                stats['created'] += 1

        return stats

    def get_by_keyword(self, keyword: str, min_followers: Optional[int] = None) -> List[TikTokUser]:
        """키워드로 사용자 목록 조회"""
        query = self.session.query(TikTokUser).filter(
            and_(
                TikTokUser.keyword == keyword,
                TikTokUser.deleted_at.is_(None)
            )
        )

        if min_followers:
            query = query.filter(TikTokUser.followers >= min_followers)

        query = query.order_by(TikTokUser.followers.desc())

        return query.all()

    def soft_delete(self, user_id: int) -> bool:
        """소프트 삭제 (deleted_at 설정)"""
        user = self.session.query(TikTokUser).filter(
            and_(
                TikTokUser.id == user_id,
                TikTokUser.deleted_at.is_(None)
            )
        ).first()

        if user:
            user.deleted_at = datetime.now()
            self.session.commit()
            return True

        return False


# MessageStatus enum은 제거됨 - 대신 result 필드에 문자열로 저장


class TikTokMessageLog(Base):
    """TikTok 메시지 전송 로그 모델"""
    
    __tablename__ = 'tiktok_message_logs'
    
    id = Column(BigInteger, primary_key=True, autoincrement=True)
    tiktok_user_id = Column(BigInteger, nullable=True, comment='틱톡사용자 id')  # tiktok_users.id 참조하므로 BigInteger
    tiktok_message_id = Column(BigInteger, nullable=True, comment='틱톡 메시지 id')  # tiktok_messages.id 참조하므로 BigInteger  
    message_text = Column(String(255), nullable=False, default='', comment='보낸 메시지 내용')
    tiktok_sender_id = Column(Integer, nullable=True, comment='틱톡 발신자 id')  # int 유지
    result = Column(String(255), nullable=True, comment='전송결과')
    result_text = Column(String(255), nullable=True, comment='전송결과 메시지')
    created_at = Column(TIMESTAMP, server_default=func.now(), nullable=False)
    updated_at = Column(TIMESTAMP, server_default=func.now(), onupdate=func.now(), nullable=False)
    
    def __repr__(self):
        return f"<TikTokMessageLog(id={self.id}, message_id={self.tiktok_message_id}, result='{self.result}')>"
    
    def to_dict(self):
        """모델을 딕셔너리로 변환"""
        return {
            'id': self.id,
            'tiktok_user_id': self.tiktok_user_id,
            'tiktok_message_id': self.tiktok_message_id,
            'message_text': self.message_text,
            'tiktok_sender_id': self.tiktok_sender_id,
            'result': self.result,
            'result_text': self.result_text,
            'created_at': self.created_at.isoformat() if self.created_at else None,
            'updated_at': self.updated_at.isoformat() if self.updated_at else None
        }


class TikTokSender(Base):
    """TikTok 발신자 정보 모델"""
    
    __tablename__ = 'tiktok_senders'
    
    id = Column(BigInteger, primary_key=True, autoincrement=True)
    nickname = Column(String(255), nullable=True, comment='별칭')
    name = Column(String(255), nullable=True, comment='계정이름')
    login_id = Column(String(255), nullable=True, comment='로그인 아이디')
    login_password = Column(String(255), nullable=True, comment='로그인 패스워드')
    session_file_path = Column(String(255), nullable=True, comment='틱톡 세션 파일 경로')
    session_updated_at = Column(TIMESTAMP, nullable=True, comment='세션 갱신 시간')
    sort = Column(Integer, nullable=True, comment='정렬기준')
    created_at = Column(TIMESTAMP, nullable=True)
    updated_at = Column(TIMESTAMP, nullable=True)
    
    def __repr__(self):
        return f"<TikTokSender(id={self.id}, nickname='{self.nickname}', login_id='{self.login_id}')>"
    
    def to_dict(self):
        """모델을 딕셔너리로 변환"""
        return {
            'id': self.id,
            'nickname': self.nickname,
            'name': self.name,
            'login_id': self.login_id,
            'session_file_path': self.session_file_path,
            'session_updated_at': self.session_updated_at.isoformat() if self.session_updated_at else None,
            'sort': self.sort,
            'created_at': self.created_at.isoformat() if self.created_at else None,
            'updated_at': self.updated_at.isoformat() if self.updated_at else None
        }


class TikTokMessageTemplate(Base):
    """TikTok 메시지 템플릿 모델"""
    
    __tablename__ = 'tiktok_message_templates'
    
    id = Column(BigInteger, primary_key=True, autoincrement=True)
    title = Column(String(255), nullable=True, comment='제목')
    message_header_json = Column(Text, nullable=True, comment='메시지 상단 메시지목록 json')
    message_body_json = Column(Text, nullable=True, comment='메시지 내용 메시지목록 json')
    message_footer_json = Column(Text, nullable=True, comment='메시지 하단 메시지목록 json')
    template_code = Column(String(255), nullable=True, comment='틱톡 메시지 템플릿코드')
    created_at = Column(TIMESTAMP, nullable=True)
    updated_at = Column(TIMESTAMP, nullable=True)
    
    def __repr__(self):
        return f"<TikTokMessageTemplate(id={self.id}, template_code='{self.template_code}', title='{self.title}')>"
    
    def to_dict(self):
        """모델을 딕셔너리로 변환"""
        return {
            'id': self.id,
            'title': self.title,
            'message_header_json': self.message_header_json,
            'message_body_json': self.message_body_json,
            'message_footer_json': self.message_footer_json,
            'template_code': self.template_code,
            'created_at': self.created_at.isoformat() if self.created_at else None,
            'updated_at': self.updated_at.isoformat() if self.updated_at else None
        }


class TikTokVideo(Base):
    """TikTok 비디오 정보 모델"""
    
    __tablename__ = 'tiktok_videos'
    
    id = Column(BigInteger, primary_key=True, autoincrement=True)
    tiktok_user_id = Column(BigInteger, nullable=False, comment='틱톡 사용자 ID')
    video_url = Column(String(255), nullable=False, comment='동영상 주소')
    title = Column(String(255), nullable=False, comment='제목')
    thumbnail_url = Column(String(255), nullable=True, comment='썸네일 주소')
    view_count = Column(BigInteger, nullable=False, default=0, comment='조회수')
    posted_at = Column(TIMESTAMP, nullable=True, comment='게시일')
    like_count = Column(BigInteger, nullable=False, default=0, comment='좋아요수')
    comment_count = Column(BigInteger, nullable=False, default=0, comment='댓글 수')
    share_count = Column(BigInteger, nullable=False, default=0, comment='공유 수')
    created_at = Column(TIMESTAMP, server_default=func.now(), nullable=True)
    updated_at = Column(TIMESTAMP, server_default=func.now(), onupdate=func.now(), nullable=True)
    
    def __repr__(self):
        return f"<TikTokVideo(id={self.id}, title='{self.title}', view_count={self.view_count})>"
    
    def to_dict(self):
        """모델을 딕셔너리로 변환"""
        return {
            'id': self.id,
            'tiktok_user_id': self.tiktok_user_id,
            'video_url': self.video_url,
            'title': self.title,
            'thumbnail_url': self.thumbnail_url,
            'view_count': self.view_count,
            'posted_at': self.posted_at.isoformat() if self.posted_at else None,
            'like_count': self.like_count,
            'comment_count': self.comment_count,
            'share_count': self.share_count,
            'created_at': self.created_at.isoformat() if self.created_at else None,
            'updated_at': self.updated_at.isoformat() if self.updated_at else None
        }
    
    @classmethod
    def from_scrape_data(cls, data: dict, tiktok_user_id: int):
        """스크래핑 데이터로부터 모델 인스턴스 생성"""
        return cls(
            tiktok_user_id=tiktok_user_id,
            video_url=data.get('link', ''),
            title=data.get('alt', ''),
            thumbnail_url=data.get('src', ''),
            view_count=data.get('views', 0),
            created_at=datetime.now()  # 현재 시간으로 created_at 설정
        )


class TikTokUploadRequest(Base):
    """TikTok 업로드 요청 모델"""
    
    __tablename__ = 'tiktok_upload_requests'
    
    id = Column(BigInteger, primary_key=True, autoincrement=True)
    tiktok_user_id = Column(BigInteger, nullable=False, comment='틱톡 사용자 ID')
    request_content = Column(Text, nullable=False, comment='요청사항')
    request_tags = Column(Text, nullable=True, comment='요청 태그')
    requested_at = Column(TIMESTAMP, nullable=False, comment='요청일시')
    deadline_date = Column(TIMESTAMP, nullable=True, comment='게시 기한')
    is_uploaded = Column(Boolean, nullable=False, default=False, comment='업로드 여부')
    is_confirm = Column(Boolean, nullable=False, default=False, comment='담당자확인 여부')
    upload_url = Column(String(255), nullable=True, comment='업로드 URL')
    upload_thumbnail_url = Column(String(255), nullable=True, comment='업로드 썸네일 URL')
    uploaded_at = Column(TIMESTAMP, nullable=True, comment='업로드 일시')
    tiktok_video_id = Column(BigInteger, nullable=True, comment='틱톡 비디오 ID')
    created_at = Column(TIMESTAMP, server_default=func.now(), nullable=True)
    updated_at = Column(TIMESTAMP, server_default=func.now(), onupdate=func.now(), nullable=True)
    
    def __repr__(self):
        return f"<TikTokUploadRequest(id={self.id}, user_id={self.tiktok_user_id}, is_uploaded={self.is_uploaded})>"
    
    def to_dict(self):
        """모델을 딕셔너리로 변환"""
        return {
            'id': self.id,
            'tiktok_user_id': self.tiktok_user_id,
            'request_content': self.request_content,
            'request_tags': self.request_tags,
            'requested_at': self.requested_at.isoformat() if self.requested_at else None,
            'deadline_date': self.deadline_date.isoformat() if self.deadline_date else None,
            'is_uploaded': self.is_uploaded,
            'upload_url': self.upload_url,
            'upload_thumbnail_url': self.upload_thumbnail_url,
            'uploaded_at': self.uploaded_at.isoformat() if self.uploaded_at else None,
            'tiktok_video_id': self.tiktok_video_id,
            'created_at': self.created_at.isoformat() if self.created_at else None,
            'updated_at': self.updated_at.isoformat() if self.updated_at else None
        }


class TikTokMessage(Base):
    """TikTok 메시지 캠페인 모델"""
    
    __tablename__ = 'tiktok_messages'
    
    id = Column(BigInteger, primary_key=True, autoincrement=True)
    tiktok_sender_id = Column(Integer, nullable=True, comment='틱톡 sender_id')
    tiktok_message_template_id = Column(BigInteger, nullable=True, comment='틱톡 메시지 템플릿 id')
    title = Column(String(255), nullable=True, comment='제목')
    is_auto = Column(Boolean, server_default='1', nullable=False, comment='자동발송 여부')
    is_complete = Column(Boolean, nullable=True, comment='전송완료여부')
    send_status = Column(String(20), nullable=False, server_default='pending', comment='전송 상태: pending(미전송), sending(전송중), completed(전송완료)')
    success_count = Column(Integer, nullable=False, server_default='0', comment='전송 성공 인원수')
    fail_count = Column(Integer, nullable=False, server_default='0', comment='전송 실패 인원수')
    start_at = Column(TIMESTAMP, nullable=True, comment='메시지 전송시작시간')
    end_at = Column(TIMESTAMP, nullable=True, comment='메시지 전송종료시간')
    created_at = Column(TIMESTAMP, nullable=True)
    updated_at = Column(TIMESTAMP, nullable=True)
    tiktok_user_id = Column(BigInteger, nullable=True, comment='틱톡 유저 id')
    
    def __repr__(self):
        return f"<TikTokMessage(id={self.id}, title='{self.title}', send_status='{self.send_status}', success={self.success_count}, fail={self.fail_count})>"
    
    def to_dict(self):
        """모델을 딕셔너리로 변환"""
        return {
            'id': self.id,
            'tiktok_sender_id': self.tiktok_sender_id,
            'tiktok_message_template_id': self.tiktok_message_template_id,
            'title': self.title,
            'is_auto': self.is_auto,
            'is_complete': self.is_complete,
            'send_status': self.send_status,
            'success_count': self.success_count,
            'fail_count': self.fail_count,
            'start_at': self.start_at.isoformat() if self.start_at else None,
            'end_at': self.end_at.isoformat() if self.end_at else None,
            'created_at': self.created_at.isoformat() if self.created_at else None,
            'updated_at': self.updated_at.isoformat() if self.updated_at else None,
            'tiktok_user_id': self.tiktok_user_id
        }


class TikTokBrandAccount(Base):
    """TikTok 브랜드 계정 모델"""
    
    __tablename__ = 'tiktok_brand_accounts'
    
    id = Column(BigInteger, primary_key=True, autoincrement=True)
    username = Column(String(255), nullable=False, unique=True, comment='브랜드 틱톡 계정명')
    brand_name = Column(String(255), nullable=False, comment='브랜드명')
    country = Column(String(2), nullable=True, comment='국적 (ISO 3166-1 alpha-2)')
    category = Column(String(255), nullable=True, comment='브랜드 카테고리')
    nickname = Column(String(255), nullable=True, comment='표시 이름')
    followers = Column(Integer, nullable=False, default=0, comment='팔로워 수')
    following_count = Column(Integer, nullable=False, default=0, comment='팔로잉 수')
    video_count = Column(Integer, nullable=False, default=0, comment='비디오 수')
    profile_url = Column(Text, nullable=True, comment='프로필 URL')
    profile_image = Column(Text, nullable=True, comment='프로필 이미지 URL')
    bio = Column(Text, nullable=True, comment='계정 소개')
    is_verified = Column(Boolean, nullable=False, default=False, comment='공식 인증 여부')
    last_scraped_at = Column(TIMESTAMP, nullable=True, comment='마지막 스크랩 시간')
    repost_accounts = Column(JSON, nullable=True, comment='리포스트 계정 목록')
    status = Column(String(20), nullable=False, default='active', comment='계정 상태')
    memo = Column(String(255), nullable=True, comment='비고')
    created_at = Column(TIMESTAMP, server_default=func.now(), nullable=True)
    updated_at = Column(TIMESTAMP, server_default=func.now(), onupdate=func.now(), nullable=True)
    
    def __repr__(self):
        return f"<TikTokBrandAccount(username='{self.username}', brand_name='{self.brand_name}', followers={self.followers})>"
    
    def to_dict(self):
        """모델을 딕셔너리로 변환"""
        return {
            'id': self.id,
            'username': self.username,
            'brand_name': self.brand_name,
            'country': self.country,
            'category': self.category,
            'nickname': self.nickname,
            'followers': self.followers,
            'following_count': self.following_count,
            'video_count': self.video_count,
            'profile_url': self.profile_url,
            'profile_image': self.profile_image,
            'bio': self.bio,
            'is_verified': self.is_verified,
            'last_scraped_at': self.last_scraped_at.isoformat() if self.last_scraped_at else None,
            'repost_accounts': self.repost_accounts,
            'status': self.status,
            'memo': self.memo,
            'created_at': self.created_at.isoformat() if self.created_at else None,
            'updated_at': self.updated_at.isoformat() if self.updated_at else None
        }


class TikTokRepostVideo(Base):
    """TikTok 리포스트 비디오 모델"""
    
    __tablename__ = 'tiktok_repost_videos'
    
    id = Column(BigInteger, primary_key=True, autoincrement=True)
    tiktok_brand_account_id = Column(BigInteger, nullable=False, comment='브랜드 계정 ID')
    video_url = Column(String(255), nullable=False, comment='동영상 주소')
    title = Column(String(255), nullable=False, comment='제목')
    thumbnail_url = Column(String(255), nullable=True, comment='썸네일 주소')
    view_count = Column(BigInteger, nullable=False, default=0, comment='조회수')
    posted_at = Column(TIMESTAMP, nullable=True, comment='게시일')
    like_count = Column(BigInteger, nullable=False, default=0, comment='좋아요수')
    comment_count = Column(BigInteger, nullable=False, default=0, comment='댓글 수')
    share_count = Column(Integer, nullable=False, default=0, comment='공유 수')
    repost_username = Column(String(255), nullable=False, comment='리포스트한 사용자 계정명')
    original_video_id = Column(String(255), nullable=True, comment='원본 비디오 ID')
    original_username = Column(String(255), nullable=True, comment='원본 비디오 계정명')
    hashtags = Column(JSON, nullable=True, comment='해시태그 목록')
    scraped_at = Column(TIMESTAMP, nullable=True, comment='스크랩 시간')
    status = Column(String(20), nullable=False, default='active', comment='비디오 상태')
    is_checked = Column(String(1), nullable=False, default='N', comment='영상 확인 여부 (Y/N)')
    created_at = Column(TIMESTAMP, server_default=func.now(), nullable=True)
    updated_at = Column(TIMESTAMP, server_default=func.now(), onupdate=func.now(), nullable=True)
    
    def __repr__(self):
        return f"<TikTokRepostVideo(id={self.id}, repost_username='{self.repost_username}', original_username='{self.original_username}')>"
    
    def to_dict(self):
        """모델을 딕셔너리로 변환"""
        return {
            'id': self.id,
            'tiktok_brand_account_id': self.tiktok_brand_account_id,
            'video_url': self.video_url,
            'title': self.title,
            'thumbnail_url': self.thumbnail_url,
            'view_count': self.view_count,
            'posted_at': self.posted_at.isoformat() if self.posted_at else None,
            'like_count': self.like_count,
            'comment_count': self.comment_count,
            'share_count': self.share_count,
            'repost_username': self.repost_username,
            'original_video_id': self.original_video_id,
            'original_username': self.original_username,
            'hashtags': self.hashtags,
            'scraped_at': self.scraped_at.isoformat() if self.scraped_at else None,
            'status': self.status,
            'is_checked': self.is_checked,
            'created_at': self.created_at.isoformat() if self.created_at else None,
            'updated_at': self.updated_at.isoformat() if self.updated_at else None
        }
    
    @classmethod
    def from_scrape_data(cls, data: dict, brand_account_id: int):
        """스크래핑 데이터로부터 모델 인스턴스 생성"""
        return cls(
            tiktok_brand_account_id=brand_account_id,
            video_url=data.get('video_url', ''),
            title=data.get('title', ''),
            thumbnail_url=data.get('thumbnail_url'),
            view_count=data.get('view_count', 0),
            posted_at=data.get('posted_at'),
            like_count=data.get('like_count', 0),
            comment_count=data.get('comment_count', 0),
            share_count=data.get('share_count', 0),
            repost_username=data.get('repost_username', ''),
            original_video_id=data.get('original_video_id'),
            original_username=data.get('original_username'),
            hashtags=data.get('hashtags'),
            scraped_at=datetime.now(),
            status=data.get('status', 'active'),
            is_checked=data.get('is_checked', 'N'),
            created_at=datetime.now(),
            updated_at=datetime.now()
        )