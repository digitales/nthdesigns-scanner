FROM mcr.microsoft.com/playwright:v1.52.0-noble

WORKDIR /app

COPY scripts/package.json scripts/package-lock.json ./scripts/
RUN cd scripts && npm ci

COPY scripts/ ./scripts/

ENV PORT=8080
EXPOSE 8080

CMD ["node", "scripts/browser-service/server.mjs"]
