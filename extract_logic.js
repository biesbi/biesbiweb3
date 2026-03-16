
const fs = require('fs');
const content = fs.readFileSync('c:/xampp/htdocs/assets/index-BxoXtaFZ.js', 'utf8');
const searchStr = 'modern-header';
const index = content.indexOf(searchStr);
if (index >= 0) {
    const start = Math.max(0, index - 500);
    const end = Math.min(content.length, index + 3000);
    console.log(content.substring(start, end));
} else {
    console.log('Not found');
}
