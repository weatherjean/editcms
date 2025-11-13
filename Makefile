.PHONY: develop backend frontend install build clean help

# Default target
help:
	@echo "Available commands:"
	@echo "  make develop    - Start both backend and frontend dev servers"
	@echo "  make backend    - Start PHP backend only (port 8000)"
	@echo "  make frontend   - Start Vue frontend only (port 5173)"
	@echo "  make install    - Install frontend dependencies"
	@echo "  make build      - Build frontend for production"
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
	@cd _edit/admin-source && npm install
	@echo "Done! Run 'make develop' to start development servers."

# Build for production
build:
	@echo "Building frontend for production..."
	@cd _edit/admin-source && npm run build
	@echo "Build complete! Files in _edit/admin/dist/"

# Clean build artifacts
clean:
	@echo "Cleaning build artifacts..."
	@rm -rf _edit/admin/dist
	@echo "Clean complete!"
