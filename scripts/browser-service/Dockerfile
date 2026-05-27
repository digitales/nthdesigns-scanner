FROM mcr.microsoft.com/playwright:v1.52.0-noble

WORKDIR /app

COPY scripts/package.json scripts/package-lock.json ./scripts/
RUN cd scripts && npm ci

COPY scripts/ ./scripts/

RUN chmod +x scripts/browser-service/start.sh

# Run as root on Fly so the app can bind :8080 and write temp dirs reliably.
# Playwright launches use --no-sandbox in scripts/browser.js.
USER root

ENV PORT=8080
ENV NODE_ENV=production
ENV PLAYWRIGHT_BROWSERS_PATH=/ms-playwright

EXPOSE 8080

CMD ["/app/scripts/browser-service/start.sh"]
