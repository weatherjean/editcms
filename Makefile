.PHONY: develop backend frontend install build clean help

# Default target
help:
	@echo "Available commands:"
	@echo "  make develop    - Start both backend and frontend dev servers"
	@echo "  make backend    - Start PHP backend only (port 8000)"
	@echo "  make frontend   - Start Vue frontend only (port 5173)"
	@echo "  make install    - Install frontend dependencies"
	@echo "  make build      - Build complete distribution"
	@echo "  make clean      - Clean build artifacts"

# Start both servers in parallel
develop:
	@echo "Starting _edit CMS development servers..."
	@echo ""
	@echo "Backend:  http://localhost:8000"
	@echo "Frontend: http://localhost:5173/_edit/admin/"
	@echo ""
	@echo "Press Ctrl+C to stop both servers"
	@echo ""
	@trap 'kill 0' SIGINT; \
	$(MAKE) backend & \
	$(MAKE) frontend & \
	wait

# PHP Backend
backend:
	@echo "Starting PHP backend on http://localhost:8000"
	@php -S localhost:8000 router.php

# Vue Frontend
frontend:
	@echo "Starting Vue frontend on http://localhost:5173"
	@cd _edit/admin-source && npm run dev

# Install dependencies
install:
	@echo "Installing frontend dependencies..."
	@cd _edit/admin-source && npm ci
	@echo "Done! Run 'make develop' to start development servers."

# Build for production
build:
	@echo "Building complete distribution..."
	@bash build.sh
	@echo "Build complete! Archive in dist/_edit-dev.zip"

# Clean build artifacts
clean:
	@echo "Cleaning build artifacts..."
	@rm -rf _edit/admin
	@echo "Clean complete!"
