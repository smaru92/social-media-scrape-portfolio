"""
TikTok 메시지 템플릿 및 메시지 전송 관련 유틸리티 클래스
"""
import os
import json
import random
import pymysql
from typing import Dict, List, Optional


class TikTokMessageTemplateManager:
    """TikTok 메시지 템플릿 관리 클래스"""
    
    def __init__(self):
        self.cached_templates = {
            "headers": [],
            "bodies": [],
            "footers": []
        }
    
    def load_message_templates(self, template_code: str = None) -> None:
        """
        DB에서 메시지 템플릿을 로드하여 캐싱
        
        Args:
            template_code: 템플릿 코드 (필수)
        """
        # template_code가 없으면 예외 발생
        if not template_code:
            raise ValueError("템플릿 코드가 필요합니다. template_code를 지정해주세요.")
            
        try:
            # DB 연결 정보 (환경변수에서 가져오기)
            db_config = {
                "host": os.getenv("DB_HOST", "localhost"),
                "user": os.getenv("DB_USER", "root"),
                "password": os.getenv("DB_PASSWORD", ""),
                "database": os.getenv("DB_NAME", "instagram"),
                "charset": "utf8mb4"
            }
            
            conn = pymysql.connect(**db_config)
            cursor = conn.cursor()
            
            # template_code로 템플릿 조회
            cursor.execute("""
                SELECT message_header_json, message_body_json, message_footer_json 
                FROM tiktok_message_templates
                WHERE template_code = %s
                LIMIT 1
            """, (template_code,))
            
            result = cursor.fetchone()
            
            if result:
                self.cached_templates["headers"] = json.loads(result[0]) if result[0] else []
                self.cached_templates["bodies"] = json.loads(result[1]) if result[1] else []
                self.cached_templates["footers"] = json.loads(result[2]) if result[2] else []
                print(f"[SUCCESS] 메시지 템플릿 로드 완료 (템플릿 코드: {template_code}): Header({len(self.cached_templates['headers'])}개), Body({len(self.cached_templates['bodies'])}개), Footer({len(self.cached_templates['footers'])}개)")
            else:
                # template_code로 검색했는데 결과가 없는 경우 예외 발생
                raise ValueError(f"템플릿 코드 '{template_code}'를 찾을 수 없습니다.")
                
            cursor.close()
            conn.close()
            
        except Exception as e:
            print(f"[ERROR] 메시지 템플릿 로드 실패: {e}")
            raise
    
    def get_random_message_template(self) -> str:
        """
        캐시된 템플릿 데이터에서 랜덤하게 조합하여 메시지를 생성
        
        Returns:
            조합된 메시지 문자열
        """
        # 캐시된 데이터가 없으면 기본 메시지 반환
        if not any(self.cached_templates.values()):
            print("[WARNING] 캐시된 템플릿이 없습니다. 기본 메시지를 사용합니다.")
            return "안녕하세요! 오늘도 좋은하루 보내세요~!\n컨텐츠 잘 보고 있어요!!!"
        
        # 각 부분에서 랜덤 선택
        header = ""
        body = ""
        footer = ""
        
        if self.cached_templates["headers"]:
            header_dict = random.choice(self.cached_templates["headers"])
            header = header_dict.get('text', '')
            
        if self.cached_templates["bodies"]:
            body_dict = random.choice(self.cached_templates["bodies"])
            body = body_dict.get('text', '')
            
        if self.cached_templates["footers"]:
            footer_dict = random.choice(self.cached_templates["footers"])
            footer = footer_dict.get('text', '')
        
        # 메시지 조합
        message_parts = []
        if header:
            message_parts.append(header)
        if body:
            message_parts.append(body)
        if footer:
            message_parts.append(footer)
        
        # 각 부분을 띄어쓰기로 연결
        message = " ".join(message_parts)
        
        print(f"[SUCCESS] 랜덤 메시지 템플릿 생성 완료")
        print(f"Header: {header[:30]}..." if header else "Header: 없음")
        print(f"Body: {body[:30]}..." if body else "Body: 없음")
        print(f"Footer: {footer[:30]}..." if footer else "Footer: 없음")
        
        return message


class TikTokMessageCounter:
    """TikTok 메시지 전송 카운트 관리 클래스"""
    
    @staticmethod
    def update_message_count(db_session, message_id: int, is_success: bool) -> None:
        """
        메시지 전송 후 성공/실패 카운트를 실시간으로 업데이트
        
        Args:
            db_session: 데이터베이스 세션
            message_id: 메시지 ID
            is_success: 성공 여부 (True: 성공, False: 실패)
        """
        if not db_session:
            return
            
        try:
            from app.models.tiktok import TikTokMessage
            
            message = db_session.query(TikTokMessage).filter(
                TikTokMessage.id == message_id
            ).first()
            
            if message:
                if is_success:
                    message.success_count = (message.success_count or 0) + 1
                    print(f"[SUCCESS COUNT UPDATE] Message {message_id}: success_count = {message.success_count}")
                else:
                    message.fail_count = (message.fail_count or 0) + 1
                    print(f"[FAIL COUNT UPDATE] Message {message_id}: fail_count = {message.fail_count}")
                
                db_session.commit()
                
        except Exception as e:
            print(f"[ERROR] 메시지 카운트 업데이트 실패: {e}")
            if db_session:
                db_session.rollback()


class TikTokMessageLogger:
    """TikTok 메시지 전송 로그 관리 클래스"""
    
    @staticmethod
    def upsert_message_log(db_session, tiktok_user_id: int, tiktok_message_id: int, 
                          message_text: str, result: str, result_text: str = None, 
                          tiktok_sender_id: int = None):
        """메시지 전송 로그를 데이터베이스에 저장하거나 업데이트"""
        if not db_session:
            return
        
        def _save_message_log():
            try:
                from app.models.tiktok import TikTokMessageLog
                
                existing_log = db_session.query(TikTokMessageLog).filter(
                    TikTokMessageLog.tiktok_user_id == tiktok_user_id,
                    TikTokMessageLog.tiktok_message_id == tiktok_message_id
                ).first()
                
                if existing_log:
                    existing_log.message_text = message_text
                    existing_log.result = result
                    existing_log.result_text = result_text
                    if tiktok_sender_id:
                        existing_log.tiktok_sender_id = tiktok_sender_id
                else:
                    new_log = TikTokMessageLog(
                        tiktok_user_id=tiktok_user_id,
                        tiktok_message_id=tiktok_message_id,
                        message_text=message_text,
                        result=result,
                        result_text=result_text,
                        tiktok_sender_id=tiktok_sender_id
                    )
                    db_session.add(new_log)
                
                db_session.commit()
                return True
                
            except Exception as e:
                print(f"[ERROR] 메시지 로그 저장 실패: {e}")
                db_session.rollback()
                return False
        
        return _save_message_log()


class TikTokMessageProcessor:
    """TikTok 메시지 처리 상태 관리 클래스"""
    
    @staticmethod
    def check_and_mark_message_processing(db_session, message_id: int) -> Dict:
        """
        메시지 전송 시작 전 상태 체크 및 처리 상태로 변경
        
        Args:
            db_session: 데이터베이스 세션
            message_id: 메시지 ID
            
        Returns:
            Dict: 처리 결과 정보
        """
        if not db_session:
            return {"success": False, "message": "DB 세션이 없습니다."}
        
        try:
            from app.models.tiktok import TikTokMessage
            
            message = db_session.query(TikTokMessage).filter(
                TikTokMessage.id == message_id
            ).first()
            
            if not message:
                return {"success": False, "message": f"메시지 ID {message_id}를 찾을 수 없습니다."}
            
            # 이미 전송 완료된 메시지인지 확인
            if message.send_status == 'completed':
                return {"success": False, "message": f"메시지 ID {message_id}는 이미 전송 완료된 메시지입니다."}
            
            # 현재 전송중인 메시지인지 확인
            if message.send_status == 'sending':
                return {"success": False, "message": f"메시지 ID {message_id}는 현재 전송중입니다."}
            
            # 전송 시작 상태로 변경
            from sqlalchemy import func
            message.send_status = 'sending'
            message.start_at = func.now()
            
            db_session.commit()
            
            return {"success": True, "message": f"메시지 ID {message_id} 전송 시작"}
            
        except Exception as e:
            print(f"[ERROR] 메시지 처리 상태 체크/변경 실패: {e}")
            if db_session:
                db_session.rollback()
            return {"success": False, "message": f"처리 중 오류 발생: {e}"}
    
    @staticmethod
    def complete_message_processing(db_session, message_id: int, success: bool) -> None:
        """
        메시지 전송 완료 후 상태를 완료로 변경
        
        Args:
            db_session: 데이터베이스 세션
            message_id: 메시지 ID
            success: 전송 성공 여부
        """
        if not db_session:
            return
        
        try:
            from app.models.tiktok import TikTokMessage
            from sqlalchemy import func
            
            message = db_session.query(TikTokMessage).filter(
                TikTokMessage.id == message_id
            ).first()
            
            if message:
                message.send_status = 'completed'
                message.end_at = func.now()
                message.is_complete = success
                
                db_session.commit()
                print(f"[MESSAGE COMPLETE] Message {message_id} 전송 완료 처리")
                
        except Exception as e:
            print(f"[ERROR] 메시지 완료 처리 실패: {e}")
            if db_session:
                db_session.rollback()