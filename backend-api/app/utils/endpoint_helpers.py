"""
TikTok 엔드포인트 공통 유틸리티 함수들
"""
import asyncio
import traceback
from concurrent.futures import ThreadPoolExecutor
from datetime import datetime
from typing import Dict, Any, Optional
from sqlalchemy.orm import Session

from app.services.tiktok_service import TikTokService


async def execute_tiktok_service(
    db: Session, 
    method_name: str, 
    *args, 
    **kwargs
) -> Dict[str, Any]:
    """
    TikTokService 메서드를 비동기적으로 실행하는 공통 함수
    
    Args:
        db: 데이터베이스 세션
        method_name: 실행할 TikTokService 메서드명
        *args: 메서드에 전달할 위치 인수
        **kwargs: 메서드에 전달할 키워드 인수
        
    Returns:
        메서드 실행 결과
        
    Raises:
        Exception: 메서드 실행 중 오류 발생 시
    """
    try:
        tiktok_service = TikTokService(db_session=db)
        method = getattr(tiktok_service, method_name)
        
        # Windows 호환성을 위해 동기 함수를 ThreadPoolExecutor에서 실행
        loop = asyncio.get_event_loop()
        with ThreadPoolExecutor() as executor:
            result = await loop.run_in_executor(
                executor,
                method,
                *args,
                **kwargs
            )
        
        return result
    except Exception as e:
        print(f"Error executing {method_name}: {e}")
        traceback.print_exc()
        raise


def get_session_file_path(
    db: Session, 
    sender_id: Optional[int], 
    fallback_file: Optional[str] = None
) -> Optional[str]:
    """
    sender_id로 세션 파일 경로를 조회하는 공통 함수
    
    Args:
        db: 데이터베이스 세션
        sender_id: TikTok 발신자 ID
        fallback_file: sender_id로 찾을 수 없을 때 사용할 기본 파일
        
    Returns:
        세션 파일 경로 또는 None
    """
    if not sender_id:
        return fallback_file
    
    try:
        from app.models.tiktok import TikTokSender
        
        sender = db.query(TikTokSender).filter(
            TikTokSender.id == sender_id
        ).first()
        
        if sender and sender.session_file_path:
            print(f"Using session file from sender ID {sender_id}: {sender.session_file_path}")
            return sender.session_file_path
        else:
            print(f"Sender ID {sender_id} not found or no session file path.")
            return fallback_file
            
    except Exception as e:
        print(f"Error getting session file path for sender {sender_id}: {e}")
        return fallback_file


def handle_endpoint_error(e: Exception, context: str) -> Dict[str, Any]:
    """
    엔드포인트 에러 처리를 위한 공통 함수
    
    Args:
        e: 발생한 예외
        context: 에러 발생 컨텍스트 (함수명 등)
        
    Returns:
        표준화된 에러 응답 딕셔너리
    """
    error_message = str(e)
    print(f"Error in {context}: {error_message}")
    traceback.print_exc()
    
    return {
        "success": False,
        "error": error_message,
        "message": "Internal server error",
        "context": context,
        "timestamp": datetime.now().isoformat()
    }


def create_success_response(
    data: Any, 
    message: str = "Success", 
    additional_info: Optional[Dict] = None
) -> Dict[str, Any]:
    """
    성공 응답을 위한 표준화된 응답 생성
    
    Args:
        data: 응답 데이터
        message: 성공 메시지
        additional_info: 추가 정보 딕셔너리
        
    Returns:
        표준화된 성공 응답 딕셔너리
    """
    response = {
        "success": True,
        "data": data,
        "message": message,
        "timestamp": datetime.now().isoformat()
    }
    
    if additional_info:
        response.update(additional_info)
    
    return response


def validate_session_file(session_file_path: Optional[str]) -> bool:
    """
    세션 파일 존재 여부를 확인
    
    Args:
        session_file_path: 세션 파일 경로
        
    Returns:
        파일 존재 여부
    """
    if not session_file_path:
        return False
        
    import os
    return os.path.exists(session_file_path)


class TikTokEndpointHelper:
    """TikTok 엔드포인트 공통 작업을 위한 헬퍼 클래스"""
    
    @staticmethod
    def extract_request_info(request) -> Dict[str, Any]:
        """요청에서 공통 정보를 추출"""
        return {
            "timestamp": datetime.now().isoformat(),
            "use_session": getattr(request, 'use_session', False),
            "sender_id": getattr(request, 'sender_id', None),
            "usernames_count": len(getattr(request, 'usernames', [])),
        }
    
    @staticmethod
    def prepare_video_scraping_params(
        request, 
        db: Session
    ) -> Dict[str, Any]:
        """비디오 스크래핑을 위한 파라미터 준비"""
        # 세션 파일 경로 결정
        session_file_path = get_session_file_path(
            db, 
            getattr(request, 'sender_id', None),
            getattr(request, 'session_file', None)
        )
        
        return {
            'usernames': request.usernames,
            'use_session': getattr(request, 'use_session', False),
            'session_file': session_file_path or "tiktok_auth.json"
        }