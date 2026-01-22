const defaultConfig = require("@wordpress/scripts/config/webpack.config");
const glob = require("glob");
const path = require("path");
const styleOutputFolder = "assets/css";

function getEntries(options) {
	const {
		root = "src/scss",
		include = "*.scss",
		outputFolder = "assets/css",
		blockDir = false,
	} = options;
	// get all root scss files in the src/scss folder
	const entries = glob.sync(root + "/" + include);

	// create an object with the relative output path as the key and the file path as the value
	const entriesWithKeys = entries.reduce((acc, entry) => {
		// skip the index file
		if ("index" === path.parse(entry).name) return acc;
		const outputDir = "../" + outputFolder + "/";
		const name = blockDir
			? getBlockStylesheetName(entry)
			: path.parse(entry).name;
		acc[outputDir + name] = path.resolve(entry);
		return acc;
	}, {});
	return entriesWithKeys;
}

function getBlockStylesheetName(filePath) {
	const pathParts = filePath.split(path.sep);
	if (pathParts.length) {
		const fileName = pathParts[pathParts.length - 1];
		pathParts[pathParts.length - 1] = path.parse(fileName).name;
	}
	const blockNamespace = pathParts[pathParts.length - 2];
	const blockName = pathParts[pathParts.length - 1];
	return blockNamespace + "--" + blockName;
}


var config = {
	...defaultConfig,
	entry: {
		...defaultConfig.entry(),
		...getEntries({ root: "src/js", include: "*.js", outputFolder: "assets/js" }),
		...getEntries({
			root: "src/scss",
			include: "*.scss",
			outputFolder: styleOutputFolder,
		}),
		...getEntries({
			root: "src/scss/blocks",
			include: "**/*.scss",
			outputFolder: styleOutputFolder,
			blockDir: true,
		}),
	},
	output: {
		...defaultConfig.output,
		// change the output path for blocks to the blocks/ folder
		path: path.resolve(process.cwd(), "blocks"),
		assetModuleFilename: "../src/scss/utils/[name].scss",
	},
	cache: false,
};

module.exports = config;
