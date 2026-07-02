const saved = 100000;
const target = 1000000;
const userInput = "900.001,00"; // what .currency-format produces
const dVal = parseFloat(userInput.replace(/\D/g, '')) || 0;
const depositReal = dVal / 100;
console.log("Deposit Real: ", depositReal);
console.log("Saved + Deposit: ", saved + depositReal);
console.log("Exceeds? ", (saved + depositReal > target));
