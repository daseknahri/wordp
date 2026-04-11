FROM node:22-alpine

WORKDIR /app

COPY package.json ./
COPY index.mjs ./
COPY src ./src

CMD ["node", "index.mjs"]
