// Copies browser builds of front-end libraries into assets/vendor so the
// deployed site does not depend on node_modules or third-party CDNs.
import { cpSync, mkdirSync } from 'node:fs';

const files = [
  ['node_modules/lucide/dist/umd/lucide.min.js', 'assets/vendor/lucide.min.js'],
  ['node_modules/cropperjs/dist/cropper.min.js', 'assets/vendor/cropper.min.js'],
  ['node_modules/cropperjs/dist/cropper.min.css', 'assets/vendor/cropper.min.css'],
];
mkdirSync('assets/vendor', { recursive: true });
for (const [from, to] of files) {
  cpSync(from, to);
  console.log(`copied ${from} -> ${to}`);
}
