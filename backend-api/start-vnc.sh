#!/bin/bash

# X11 가상 디스플레이 시작
Xvfb :99 -screen 0 1920x1080x24 &
export DISPLAY=:99

# Fluxbox 윈도우 매니저 시작
fluxbox &

# VNC 서버 시작 (비밀번호 없이)
x11vnc -display :99 -nopw -forever -shared &

# FastAPI 앱 실행
uvicorn app.main:app --host 0.0.0.0 --port 8000