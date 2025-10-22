"""
TikTok 서비스 커스텀 Exception 모듈

TikTokService에서 발생할 수 있는 다양한 에러 상황을 위한 
구조화된 예외 클래스들을 정의합니다.
"""

from typing import Optional, Dict, Any


class TikTokServiceException(Exception):
    """TikTok 서비스 기본 예외 클래스"""
    
    def __init__(self, message: str, error_code: str = None, details: Dict[str, Any] = None):
        super().__init__(message)
        self.message = message
        self.error_code = error_code or "TIKTOK_UNKNOWN_ERROR"
        self.details = details or {}
        
    def to_dict(self) -> Dict[str, Any]:
        """예외를 딕셔너리로 변환"""
        return {
            "error": True,
            "error_code": self.error_code,
            "message": self.message,
            "details": self.details
        }


class TikTokBrowserException(TikTokServiceException):
    """브라우저 관련 예외"""
    
    def __init__(self, message: str, browser_error: str = None):
        super().__init__(
            message=message,
            error_code="TIKTOK_BROWSER_ERROR",
            details={"browser_error": browser_error} if browser_error else {}
        )


class TikTokCaptchaException(TikTokServiceException):
    """CAPTCHA 감지 예외"""
    
    def __init__(self, username: str = None, url: str = None):
        message = f"CAPTCHA가 발생했습니다"
        if username:
            message += f" (사용자: {username})"
            
        super().__init__(
            message=message,
            error_code="TIKTOK_CAPTCHA_DETECTED",
            details={
                "username": username,
                "url": url,
                "action_required": "수동으로 CAPTCHA를 해결하거나 나중에 다시 시도하세요"
            }
        )


class TikTokUserNotFoundException(TikTokServiceException):
    """사용자를 찾을 수 없음 예외"""
    
    def __init__(self, username: str):
        super().__init__(
            message=f"사용자 '{username}'을 찾을 수 없습니다",
            error_code="TIKTOK_USER_NOT_FOUND",
            details={"username": username}
        )


class TikTokLoginRequiredException(TikTokServiceException):
    """로그인이 필요함 예외"""
    
    def __init__(self, action: str = None):
        message = "이 작업을 수행하려면 로그인이 필요합니다"
        if action:
            message += f" (작업: {action})"
            
        super().__init__(
            message=message,
            error_code="TIKTOK_LOGIN_REQUIRED",
            details={"required_action": action} if action else {}
        )


class TikTokSessionExpiredException(TikTokServiceException):
    """세션 만료 예외"""
    
    def __init__(self, session_file: str = None):
        super().__init__(
            message="TikTok 세션이 만료되었습니다. 다시 로그인하세요",
            error_code="TIKTOK_SESSION_EXPIRED",
            details={"session_file": session_file} if session_file else {}
        )


class TikTokRateLimitException(TikTokServiceException):
    """요청 제한 예외"""
    
    def __init__(self, retry_after: int = None):
        message = "TikTok API 요청 한도에 도달했습니다"
        if retry_after:
            message += f". {retry_after}초 후 다시 시도하세요"
            
        super().__init__(
            message=message,
            error_code="TIKTOK_RATE_LIMIT_EXCEEDED",
            details={"retry_after_seconds": retry_after} if retry_after else {}
        )


class TikTokScrapingException(TikTokServiceException):
    """스크래핑 관련 예외"""
    
    def __init__(self, message: str, username: str = None, step: str = None):
        super().__init__(
            message=message,
            error_code="TIKTOK_SCRAPING_ERROR",
            details={
                "username": username,
                "failed_step": step
            }
        )


class TikTokMessageException(TikTokServiceException):
    """메시지 전송 관련 예외"""
    
    def __init__(self, message: str, username: str = None, message_text: str = None):
        super().__init__(
            message=message,
            error_code="TIKTOK_MESSAGE_ERROR",
            details={
                "target_username": username,
                "message_text": message_text[:50] + "..." if message_text and len(message_text) > 50 else message_text
            }
        )


class TikTokDatabaseException(TikTokServiceException):
    """데이터베이스 관련 예외"""
    
    def __init__(self, message: str, operation: str = None, table: str = None):
        super().__init__(
            message=message,
            error_code="TIKTOK_DATABASE_ERROR",
            details={
                "operation": operation,
                "table": table
            }
        )


class TikTokValidationException(TikTokServiceException):
    """데이터 검증 예외"""
    
    def __init__(self, message: str, field: str = None, value: Any = None):
        super().__init__(
            message=message,
            error_code="TIKTOK_VALIDATION_ERROR",
            details={
                "field": field,
                "invalid_value": str(value) if value is not None else None
            }
        )


class TikTokConfigException(TikTokServiceException):
    """설정 관련 예외"""
    
    def __init__(self, message: str, config_key: str = None):
        super().__init__(
            message=message,
            error_code="TIKTOK_CONFIG_ERROR",
            details={"config_key": config_key} if config_key else {}
        )


class TikTokFileException(TikTokServiceException):
    """파일 관련 예외"""
    
    def __init__(self, message: str, file_path: str = None, operation: str = None):
        super().__init__(
            message=message,
            error_code="TIKTOK_FILE_ERROR",
            details={
                "file_path": file_path,
                "operation": operation
            }
        )


# 예외 처리를 위한 헬퍼 함수들
def handle_tiktok_exception(func):
    """TikTok 예외 처리 데코레이터"""
    def wrapper(*args, **kwargs):
        try:
            return func(*args, **kwargs)
        except TikTokServiceException:
            # 이미 TikTok 예외는 그대로 전파
            raise
        except Exception as e:
            # 기타 예외를 TikTok 예외로 변환
            raise TikTokServiceException(
                message=f"예상치 못한 오류가 발생했습니다: {str(e)}",
                error_code="TIKTOK_UNEXPECTED_ERROR",
                details={"original_error": str(e), "error_type": type(e).__name__}
            )
    return wrapper


def safe_execute(operation_name: str, func, *args, **kwargs) -> Dict[str, Any]:
    """안전한 작업 실행 헬퍼"""
    try:
        result = func(*args, **kwargs)
        return {
            "success": True,
            "data": result,
            "operation": operation_name
        }
    except TikTokServiceException as e:
        print(f"[ERROR] {operation_name}: {e.message}")
        return e.to_dict()
    except Exception as e:
        print(f"[ERROR] {operation_name}: 예상치 못한 오류 - {str(e)}")
        return TikTokServiceException(
            message=f"{operation_name} 실행 중 오류 발생: {str(e)}",
            error_code="TIKTOK_EXECUTION_ERROR"
        ).to_dict()


# 예외 상태 코드 매핑 (HTTP API 응답용)
EXCEPTION_STATUS_CODES = {
    "TIKTOK_USER_NOT_FOUND": 404,
    "TIKTOK_LOGIN_REQUIRED": 401,
    "TIKTOK_SESSION_EXPIRED": 401,
    "TIKTOK_RATE_LIMIT_EXCEEDED": 429,
    "TIKTOK_VALIDATION_ERROR": 400,
    "TIKTOK_CONFIG_ERROR": 500,
    "TIKTOK_DATABASE_ERROR": 500,
    "TIKTOK_FILE_ERROR": 500,
    "TIKTOK_BROWSER_ERROR": 500,
    "TIKTOK_CAPTCHA_DETECTED": 429,
    "TIKTOK_SCRAPING_ERROR": 500,
    "TIKTOK_MESSAGE_ERROR": 500,
    "TIKTOK_UNEXPECTED_ERROR": 500,
    "TIKTOK_EXECUTION_ERROR": 500,
}


def get_http_status_code(error_code: str) -> int:
    """에러 코드에 따른 HTTP 상태 코드 반환"""
    return EXCEPTION_STATUS_CODES.get(error_code, 500)