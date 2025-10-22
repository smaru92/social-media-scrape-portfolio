"""
TikTok 유틸리티 모듈

TikTokService에서 중복되는 로직들을 모듈화한 유틸리티 클래스들
- 데이터 파싱 유틸리티
- 시간 처리 유틸리티  
- 이미지 처리 유틸리티
- DB 관련 유틸리티
"""

import os
import re
import time
import random
import hashlib
import requests
from datetime import datetime, timedelta
from typing import Optional, Dict, List, Any
from pathlib import Path
from urllib.parse import urlparse


class TikTokDataParser:
    """TikTok 데이터 파싱 유틸리티"""

    def __init__(self):
        pass

    @staticmethod
    def parse_count(count_text: str) -> int:
        """
        팔로워, 좋아요, 댓글, 조회수 등의 텍스트를 숫자로 변환
        
        Args:
            count_text: '1.2M', '1.2K', '1234' 등의 카운트 텍스트
            
        Returns:
            int: 파싱된 카운트 수
        """
        if not count_text or not isinstance(count_text, str):
            return 0
            
        count_text = count_text.upper().strip()
        
        # 숫자만 추출
        number_match = re.search(r'([\d.]+)([KMB]?)', count_text)
        if not number_match:
            return 0
            
        number_str, unit = number_match.groups()
        
        try:
            number = float(number_str)
        except ValueError:
            return 0
            
        # 단위 변환
        multipliers = {
            'K': 1_000,
            'M': 1_000_000, 
            'B': 1_000_000_000
        }
        
        multiplier = multipliers.get(unit, 1)
        return int(number * multiplier)
    
    @staticmethod
    def parse_follower_count(text: str) -> int:
        """
        레거시 호환성을 위한 별칭 (parse_count와 동일)
        """
        return TikTokDataParser.parse_count(text)
    
    @staticmethod
    def parse_relative_date(date_text: str) -> Optional[datetime]:
        """
        상대적 날짜 텍스트를 datetime으로 변환
        
        Args:
            date_text: '3일 전', '2시간 전', '1분 전' 등의 텍스트
            
        Returns:
            Optional[datetime]: 파싱된 날짜 또는 None
        """
        if not date_text or not isinstance(date_text, str):
            return None
            
        now = datetime.now()
        date_text = date_text.lower().strip()
        
        # 분 전
        minute_match = re.search(r'(\d+)\s*분\s*전', date_text)
        if minute_match:
            minutes = int(minute_match.group(1))
            return now - timedelta(minutes=minutes)
        
        # 시간 전  
        hour_match = re.search(r'(\d+)\s*시간\s*전', date_text)
        if hour_match:
            hours = int(hour_match.group(1))
            return now - timedelta(hours=hours)
        
        # 일 전
        day_match = re.search(r'(\d+)\s*일\s*전', date_text)
        if day_match:
            days = int(day_match.group(1))
            return now - timedelta(days=days)
        
        # 주 전
        week_match = re.search(r'(\d+)\s*주\s*전', date_text)
        if week_match:
            weeks = int(week_match.group(1))
            return now - timedelta(weeks=weeks)
        
        # 개월 전 (대략적 계산)
        month_match = re.search(r'(\d+)\s*개월\s*전', date_text)
        if month_match:
            months = int(month_match.group(1))
            return now - timedelta(days=months * 30)
        
        return None
    
    @staticmethod
    def extract_hashtags(text: str) -> List[str]:
        """
        텍스트에서 해시태그를 추출
        
        Args:
            text: 해시태그가 포함된 텍스트
            
        Returns:
            List[str]: 추출된 해시태그 리스트
        """
        if not text:
            return []
            
        hashtags = re.findall(r'#[\w가-힣]+', text)
        return [tag.replace('#', '') for tag in hashtags]


class TikTokWaitUtils:
    """대기 시간 관련 유틸리티"""
    
    @staticmethod
    def random_wait(min_seconds: float, max_seconds: float):
        """랜덤 대기"""
        wait_time = random.uniform(min_seconds, max_seconds)
        time.sleep(wait_time)
        return wait_time
    
    @staticmethod
    async def async_random_wait(min_ms: int, max_ms: int, page=None):
        """비동기 랜덤 대기"""
        wait_time = random.uniform(min_ms, max_ms)
        if page:
            await page.wait_for_timeout(wait_time)
        else:
            import asyncio
            await asyncio.sleep(wait_time / 1000)
        return wait_time
    
    @staticmethod
    def human_like_delay():
        """사람처럼 보이는 자연스러운 대기"""
        return TikTokWaitUtils.random_wait(1, 3)


class TikTokImageUtils:
    """이미지 처리 관련 유틸리티"""
    
    @staticmethod
    def generate_image_filename(username: str, suffix: str = "") -> str:
        """
        이미지 파일명 생성
        
        Args:
            username: 사용자명
            suffix: 접미사
            
        Returns:
            str: 생성된 파일명
        """
        timestamp = int(time.time())
        hash_part = hashlib.md5(f"{username}_{timestamp}".encode()).hexdigest()[:8]
        
        if suffix:
            return f"{username}_{suffix}_{hash_part}_{timestamp}.jpg"
        else:
            return f"{username}_{hash_part}_{timestamp}.jpg"
    
    @staticmethod
    def create_image_directory(base_path: Path, username: str) -> Path:
        """
        사용자별 이미지 디렉토리 생성
        
        Args:
            base_path: 기본 경로
            username: 사용자명
            
        Returns:
            Path: 생성된 디렉토리 경로
        """
        user_dir = base_path / username
        user_dir.mkdir(parents=True, exist_ok=True)
        return user_dir
    
    @staticmethod
    def is_valid_image_url(url: str) -> bool:
        """이미지 URL 유효성 검사"""
        if not url or not isinstance(url, str):
            return False
        
        # 기본 URL 형식 검사
        if not url.startswith(('http://', 'https://')):
            return False
        
        # 이미지 확장자 검사
        image_extensions = ['.jpg', '.jpeg', '.png', '.gif', '.webp']
        return any(ext in url.lower() for ext in image_extensions)
    
    @staticmethod
    def download_image(image_url: str, username: str, image_type: str = "thumbnail", image_base_dir: Path = None) -> Optional[str]:
        """
        이미지를 다운로드하고 로컬에 저장합니다.
        
        Args:
            image_url: 다운로드할 이미지 URL
            username: 사용자명 (디렉토리 생성용)
            image_type: 이미지 타입 (thumbnail, profile 등)
            image_base_dir: 이미지 저장 기본 디렉토리
            
        Returns:
            로컬 이미지 경로 또는 None
        """
        if not image_url:
            return None
        
        # 기본 디렉토리 설정
        if image_base_dir is None:
            image_base_dir = Path("tiktok_images")
            
        try:
            # 사용자별 디렉토리 생성
            user_dir = image_base_dir / username
            user_dir.mkdir(exist_ok=True)
            
            # URL에서 파일명 생성 (해시 사용)
            url_hash = hashlib.md5(image_url.encode()).hexdigest()[:8]
            timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
            
            # 확장자 추출
            parsed_url = urlparse(image_url)
            path_parts = parsed_url.path.split('.')
            extension = path_parts[-1] if len(path_parts) > 1 and path_parts[-1] in ['jpg', 'jpeg', 'png', 'gif', 'webp'] else 'jpg'
            
            # 파일명 생성
            filename = f"{image_type}_{timestamp}_{url_hash}.{extension}"
            file_path = user_dir / filename
            
            # 이미지 다운로드
            headers = {
                'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Referer': 'https://www.tiktok.com/'
            }
            
            response = requests.get(image_url, headers=headers, timeout=10)
            response.raise_for_status()
            
            # 파일 저장
            with open(file_path, 'wb') as f:
                f.write(response.content)
            
            print(f"✅ 이미지 다운로드 완료: {file_path}")
            return str(file_path)
            
        except Exception as e:
            print(f"⚠️ 이미지 다운로드 실패 ({image_url[:50]}...): {e}")
            return None
    
    @staticmethod
    def upload_image_to_admin(file_path: str, username: str, record_id: int, table_type: str, admin_url: str = None) -> Optional[str]:
        """
        로컬 이미지를 관리페이지에 업로드합니다.
        
        Args:
            file_path: 업로드할 로컬 파일 경로
            username: 사용자명
            record_id: 테이블 레코드 ID
            table_type: 테이블 타입 (user, video, repost_video)
            admin_url: 관리자 페이지 URL
            
        Returns:
            업로드된 이미지 URL 또는 None
        """
        print(f"🔍 디버그 - admin_url: {admin_url}")
        print(f"🔍 디버그 - file_path: {file_path}")
        print(f"🔍 디버그 - username: {username}")
        print(f"🔍 디버그 - record_id: {record_id}")
        print(f"🔍 디버그 - table_type: {table_type}")

        if not admin_url:
            print("⚠️ ADMIN_URL이 설정되지 않았습니다.")
            return None
            
        try:
            upload_url = f"{admin_url.rstrip('/')}/api/tiktok/upload-image"
            
            # 파일명 생성
            filename = os.path.basename(file_path)
            
            # 멀티파트 폼 데이터 준비
            with open(file_path, 'rb') as f:
                files = {
                    'image': (filename, f, 'image/jpeg')
                }
                data = {
                    'table_type': table_type,
                    'tiktok_username': username,
                    'record_id': str(record_id)
                }
                
                response = requests.post(
                    upload_url,
                    files=files,
                    data=data,
                    timeout=30
                )
            
            print(f"🔍 API 응답 상태: {response.status_code}")
            print(f"🔍 API 응답 내용: {response.text}")

            if response.status_code == 200:
                result = response.json()
                # API 응답 형식: {'success': True, 'data': {'image_path': '...', ...}}
                if result.get('success') and result.get('data'):
                    uploaded_url = result['data'].get('image_path')
                    print(f"✅ 관리페이지 업로드 완료: {uploaded_url}")
                    print(f"🔍 응답 JSON: {result}")
                    return uploaded_url
                else:
                    print(f"⚠️ 관리페이지 업로드 실패: API 응답에 image_path가 없습니다")
                    print(f"🔍 응답 JSON: {result}")
                    return None
            else:
                print(f"⚠️ 관리페이지 업로드 실패: {response.status_code} - {response.text}")
                return None
                
        except Exception as e:
            print(f"⚠️ 관리페이지 업로드 실패: {e}")
            return None
    
    @staticmethod
    def upload_downloaded_image(local_path: str, username: str, record_id: int, table_type: str, admin_url: str = None) -> Optional[str]:
        """
        다운로드된 로컬 이미지를 관리페이지에 업로드합니다.
        
        Args:
            local_path: 로컬 이미지 파일 경로
            username: 사용자명
            record_id: 테이블 레코드 ID
            table_type: 테이블 타입 (user, video, repost_video)
            admin_url: 관리자 페이지 URL
            
        Returns:
            업로드된 이미지 URL 또는 None
        """
        if not local_path or not os.path.exists(local_path):
            return None
            
        return TikTokImageUtils.upload_image_to_admin(local_path, username, record_id, table_type, admin_url)


class TikTokDatabaseUtils:
    """데이터베이스 관련 유틸리티"""
    
    @staticmethod
    def safe_commit(db_session, operation_name: str = "Database operation"):
        """
        안전한 DB 커밋
        
        Args:
            db_session: SQLAlchemy 세션
            operation_name: 작업명 (로깅용)
        """
        try:
            db_session.commit()
            print(f"✅ {operation_name} 성공")
            return True
        except Exception as e:
            db_session.rollback()
            print(f"❌ {operation_name} 실패: {e}")
            return False
    
    @staticmethod
    def create_or_update_record(db_session, model_class, filters: Dict, updates: Dict):
        """
        레코드 생성 또는 업데이트
        
        Args:
            db_session: SQLAlchemy 세션
            model_class: 모델 클래스
            filters: 검색 조건
            updates: 업데이트할 데이터
            
        Returns:
            tuple: (record, is_created)
        """
        try:
            # 기존 레코드 검색
            record = db_session.query(model_class).filter_by(**filters).first()
            
            if record:
                # 업데이트
                for key, value in updates.items():
                    setattr(record, key, value)
                is_created = False
            else:
                # 생성
                record_data = {**filters, **updates}
                record = model_class(**record_data)
                db_session.add(record)
                is_created = True
            
            return record, is_created
            
        except Exception as e:
            print(f"❌ 레코드 생성/업데이트 실패: {e}")
            return None, False


class TikTokValidationUtils:
    """데이터 검증 관련 유틸리티"""
    
    @staticmethod
    def is_valid_username(username: str) -> bool:
        """TikTok 사용자명 유효성 검사"""
        if not username or not isinstance(username, str):
            return False
        
        username = username.strip().replace('@', '')
        
        # 길이 검사 (1-24자)
        if not 1 <= len(username) <= 24:
            return False
        
        # 허용된 문자만 포함하는지 검사 (영문, 숫자, 밑줄, 점)
        if not re.match(r'^[a-zA-Z0-9_.]+$', username):
            return False
        
        return True
    
    @staticmethod
    def is_valid_url(url: str) -> bool:
        """URL 유효성 검사"""
        if not url or not isinstance(url, str):
            return False
        
        url_pattern = re.compile(
            r'^https?://'  # http:// or https://
            r'(?:(?:[A-Z0-9](?:[A-Z0-9-]{0,61}[A-Z0-9])?\.)+[A-Z]{2,6}\.?|'  # domain...
            r'localhost|'  # localhost...
            r'\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})'  # ...or ip
            r'(?::\d+)?'  # optional port
            r'(?:/?|[/?]\S+)$', re.IGNORECASE)
        
        return url_pattern.match(url) is not None
    
    @staticmethod
    def sanitize_text(text: str, max_length: int = 255) -> str:
        """텍스트 정제 및 길이 제한"""
        if not text or not isinstance(text, str):
            return ""
        
        # 개행 문자 제거 및 공백 정리
        cleaned = re.sub(r'\s+', ' ', text.strip())
        
        # 길이 제한
        if len(cleaned) > max_length:
            cleaned = cleaned[:max_length].rstrip()
        
        return cleaned


class TikTokUrlUtils:
    """URL 관련 유틸리티"""
    
    @staticmethod
    def extract_username_from_url(url: str) -> Optional[str]:
        """URL에서 사용자명 추출"""
        if not url:
            return None
        
        # @username 패턴 찾기
        match = re.search(r'/@([a-zA-Z0-9_.]+)', url)
        if match:
            return match.group(1)
        
        return None
    
    @staticmethod
    def build_profile_url(username: str) -> str:
        """프로필 URL 생성"""
        username = username.replace('@', '')
        return f"https://www.tiktok.com/@{username}"
    
    @staticmethod
    def build_video_url(username: str, video_id: str) -> str:
        """비디오 URL 생성"""
        username = username.replace('@', '')
        return f"https://www.tiktok.com/@{username}/video/{video_id}"