from pydantic import BaseModel
from typing import Optional, List

class ScrapeRequest(BaseModel):
    keyword: str
    min_followers: Optional[int] = 10000
    tiktok_user_log_id: Optional[int] = None
    scrolls: Optional[int] = 9

class TikTokLoginRequest(BaseModel):
    username: str
    password: str

class SendMessageRequest(BaseModel):
    usernames: List[str]
    template_code: str  # 템플릿 코드 (필수)
    session_file_path: str  # 세션 파일 경로
    message_id: Optional[int] = None  # 메시지 ID

class UploadSessionRequest(BaseModel):
    sender_id: int
    file_name: str
    session_data: dict  # 세션 데이터 (JSON 객체)

class ScrapeVideoRequest(BaseModel):
    usernames: List[str]  # TikTok 사용자명 리스트
    use_session: Optional[bool] = True  # 세션 사용 여부
    session_file: Optional[str] = "tiktok_auth.json"  # 세션 파일 경로
    sender_id: Optional[int] = 0  # 비로그인 세션용 sender ID (기본값 0)

class CollectRepostUsersRequest(BaseModel):
    limit: Optional[int] = 10  # 처리할 최대 비디오 수
    user_agent: Optional[str] = None  # 사용할 User-Agent 문자열
    session_file: Optional[str] = "tiktok_auth.json"  # 세션 파일 경로