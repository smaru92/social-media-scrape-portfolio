from pydantic_settings import BaseSettings
from typing import Optional


class Settings(BaseSettings):
    DB_HOST: str
    DB_USER: str
    DB_PASSWORD: str
    DB_NAME: str
    DB_PORT: int = 3306
    
    # SSH 관련 설정
    SSH_HOST: Optional[str] = None
    SSH_PORT: Optional[int] = 22
    SSH_USERNAME: Optional[str] = None
    SSH_PASSWORD: Optional[str] = None
    SSH_KEY_FILE: Optional[str] = None
    SSH_REMOTE_PATH: Optional[str] = "/home/ubuntu/instagram/storage/app/tiktok_sessions/"
    
    # 관리페이지 URL
    ADMIN_URL: Optional[str] = None
    
    @property
    def SYNC_DATABASE_URL(self) -> str:
        return f"mysql+pymysql://{self.DB_USER}:{self.DB_PASSWORD}@{self.DB_HOST}:{self.DB_PORT}/{self.DB_NAME}"
    
    class Config:
        env_file = ".env"
        case_sensitive = True


settings = Settings()