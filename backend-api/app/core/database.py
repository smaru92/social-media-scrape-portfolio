from sqlalchemy import create_engine
from sqlalchemy.orm import declarative_base, sessionmaker, Session
from typing import Generator
from app.core.config import settings


Base = declarative_base()

# 동기 엔진 생성
engine = create_engine(
    settings.SYNC_DATABASE_URL,  # mysql+pymysql:// 형식 사용
    echo=False,  # DB 쿼리 로그 비활성화
    pool_pre_ping=True,
    pool_size=10,
    max_overflow=20
)

SessionLocal = sessionmaker(
    autocommit=False,
    autoflush=False,
    bind=engine
)


def get_sync_db() -> Generator[Session, None, None]:
    """동기 데이터베이스 세션 생성"""
    db = SessionLocal()
    try:
        yield db
        db.commit()
    except Exception:
        db.rollback()
        raise
    finally:
        db.close()