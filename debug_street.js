
const fs = require('fs');
const content = fs.readFileSync('c:/xampp/htdocs/assets/index-BxoXtaFZ.js', 'utf8');
const searchStr = 'name:"street"';
const index = content.indexOf(searchStr);
if (index >= 0) {
    console.log(content.substring(index - 2000, index + 500));
} else {
    console.log('Not found');
}
