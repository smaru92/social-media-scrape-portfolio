from fastapi import APIRouter
from app.api.v1.endpoints import tiktok

api_router = APIRouter()
api_router.include_router(tiktok.router, prefix="/tiktok", tags=["tiktok"])
